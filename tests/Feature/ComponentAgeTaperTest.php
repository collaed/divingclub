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

    private function component(array $overrides = []): MembershipFeeComponent
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
        $c = $this->component();
        $u = $this->member('2015-01-01');

        $this->assertSame(40.0, $this->service->componentAmount($c, $u));
    }

    public function test_minor_at_anchor_gets_free_when_ratio_zero(): void
    {
        $c = $this->component(['taper_below_age' => 18, 'taper_ratio' => 0, 'age_anchor_date' => '2027-01-01']);
        // Turns 18 during 2027 but is still 17 at the Jan 1 anchor -> free.
        $u = $this->member('2009-06-15');

        $this->assertSame(0.0, $this->service->componentAmount($c, $u));
    }

    public function test_adult_at_anchor_pays_full(): void
    {
        $c = $this->component(['taper_below_age' => 18, 'taper_ratio' => 0, 'age_anchor_date' => '2027-01-01']);
        // Already 18 at the anchor.
        $u = $this->member('2008-12-31');

        $this->assertSame(40.0, $this->service->componentAmount($c, $u));
    }

    public function test_ratio_half_applies_discount(): void
    {
        $c = $this->component(['taper_below_age' => 18, 'taper_ratio' => 0.5, 'age_anchor_date' => '2027-01-01']);
        $u = $this->member('2015-01-01');

        $this->assertSame(20.0, $this->service->componentAmount($c, $u));
    }

    public function test_null_dob_charged_full(): void
    {
        $c = $this->component(['taper_below_age' => 18, 'taper_ratio' => 0]);
        $user = User::factory()->create();
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'No', 'last_name' => 'Dob']);

        $this->assertSame(40.0, $this->service->componentAmount($c, $user->fresh()));
    }

    public function test_anchor_falls_back_to_season_start_when_null(): void
    {
        $season = Season::factory()->create(['year' => '2027', 'start_date' => '2026-09-01']);
        $c = $this->component(['taper_below_age' => 18, 'taper_ratio' => 0]);
        // 17 at 2026-09-01 season start.
        $u = $this->member('2009-06-15');

        $this->assertSame(0.0, $this->service->componentAmount($c, $u, $season));
    }

    public function test_calculate_includes_tapered_component_in_total(): void
    {
        Season::factory()->create(['year' => '2027', 'start_date' => '2026-09-01']);
        $this->component(['taper_below_age' => 18, 'taper_ratio' => 0, 'age_anchor_date' => '2027-01-01']);
        $u = $this->member('2012-05-05');

        $calc = $this->service->calculate($u, '2027', ['flassa']);

        $this->assertSame(0.0, $calc['components']['flassa']);
        $this->assertSame(0.0, $calc['amount_due']);
    }
}
