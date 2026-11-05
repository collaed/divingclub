<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\MembershipFeeComponent;
use App\Models\Season;
use App\Models\User;
use App\Services\FeeCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class ComponentAgeTaperTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private FeeCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->service = app(FeeCalculationService::class);
    }

    private function member(string $dob): User
    {
        $user = User::factory()->create();
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'A', 'last_name' => 'B', 'date_of_birth' => $dob]);

        return $user->fresh();
    }

    private function makeComponent(array $overrides = []): MembershipFeeComponent
    {
        return MembershipFeeComponent::create(array_merge([
            'name' => 'Licence FLASSA',
            'slug' => 'flassa',
            'amount' => 40.00,
            'is_optional' => true,
        ], $overrides));
    }

    public function test_no_taper_fields_returns_full_amount(): void
    {
        $c = $this->makeComponent();
        $u = $this->member('2015-01-01');

        $this->assertSame(40.0, $this->service->componentAmount($c, $u));
    }

    public function test_minor_at_anchor_gets_free_when_ratio_zero(): void
    {
        $season = Season::factory()->create(['year' => '2027']);
        $c = $this->makeComponent(['taper_below_age' => 18, 'taper_ratio' => 0]);
        // Turns 18 during 2027 but is still 17 at the Jan 1 anchor -> free.
        $u = $this->member('2009-06-15');

        $this->assertSame(0.0, $this->service->componentAmount($c, $u, $season));
    }

    public function test_adult_at_anchor_pays_full(): void
    {
        $season = Season::factory()->create(['year' => '2027']);
        $c = $this->makeComponent(['taper_below_age' => 18, 'taper_ratio' => 0]);
        // Already 18 at the anchor.
        $u = $this->member('2008-12-31');

        $this->assertSame(40.0, $this->service->componentAmount($c, $u, $season));
    }

    public function test_ratio_half_applies_discount(): void
    {
        $season = Season::factory()->create(['year' => '2027']);
        $c = $this->makeComponent(['taper_below_age' => 18, 'taper_ratio' => 0.5]);
        $u = $this->member('2015-01-01');

        $this->assertSame(20.0, $this->service->componentAmount($c, $u, $season));
    }

    public function test_null_dob_charged_full(): void
    {
        $c = $this->makeComponent(['taper_below_age' => 18, 'taper_ratio' => 0]);
        $user = User::factory()->create();
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'No', 'last_name' => 'Dob']);

        $this->assertSame(40.0, $this->service->componentAmount($c, $user->fresh()));
    }

    public function test_anchor_falls_back_to_jan_1_of_season_year_when_null(): void
    {
        // The club's season starts 2026-09-01, but the licence/taper anchor must
        // ignore that and use 2027-01-01 — this member turns 18 on 2026-10-01,
        // so they're still 17 at season start but already 18 at Jan 1.
        $season = Season::factory()->create(['year' => '2027', 'start_date' => '2026-09-01']);
        $c = $this->makeComponent(['taper_below_age' => 18, 'taper_ratio' => 0]);
        $u = $this->member('2008-10-01');

        $this->assertSame(40.0, $this->service->componentAmount($c, $u, $season));
    }

    public function test_a_stale_component_level_anchor_is_ignored_in_favour_of_jan_1(): void
    {
        // Regression: a component's own age_anchor_date (e.g. left over from an
        // old "anchor = 1 November" policy) must never win over Jan 1 of the
        // season year — this member is 15 on 2026-11-01 but 16 on 2027-01-01.
        $season = Season::factory()->create(['year' => '2027']);
        $c = $this->makeComponent(['taper_below_age' => 16, 'taper_ratio' => 0, 'age_anchor_date' => '2026-11-01']);
        $u = $this->member('2010-11-15');

        $this->assertSame(40.0, $this->service->componentAmount($c, $u, $season));
    }

    public function test_calculate_includes_tapered_component_in_total(): void
    {
        Season::factory()->create(['year' => '2027', 'start_date' => '2026-09-01']);
        $this->makeComponent(['taper_below_age' => 18, 'taper_ratio' => 0]);
        $u = $this->member('2012-05-05');

        $calc = $this->service->calculate($u, '2027', ['flassa']);

        $this->assertSame(0.0, $calc['components']['flassa']);
        $this->assertSame(0.0, $calc['amount_due']);
    }
}
