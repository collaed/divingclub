<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\MembershipFee;
use App\Models\MembershipFeeComponent;
use App\Models\MemberStatus;
use App\Models\Season;
use App\Models\User;
use App\Services\FeeCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Members younger than 18 at the licence anchor pay 50% of the nominal club
 * cotisation (rounded up to the euro); the FFESSM licence keeps its own age
 * bands (under 12: 14.50, 12 to under 16: 31.50, 16 and over: adult).
 */
#[Group('p1')]
class MinorClubFeeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Season::create(['year' => '2027', 'name' => 'Season 2027', 'start_date' => '2026-09-01', 'end_date' => '2027-08-31']);
        foreach ([['lic_adulte', 50.00], ['lic_jeune', 31.50], ['lic_enfant', 14.50]] as [$slug, $amount]) {
            MembershipFeeComponent::create(['slug' => $slug, 'name' => $slug, 'kind' => MembershipFeeComponent::KIND_FFESSM_LICENCE, 'amount' => $amount, 'is_optional' => false]);
        }
    }

    private function member(string $statusSlug, float $fee, string $dob): User
    {
        $status = MemberStatus::firstOrCreate(['slug' => $statusSlug], ['name' => $statusSlug]);
        MembershipFee::firstOrCreate(['season_year' => '2027', 'status_id' => $status->id], ['amount' => $fee, 'label' => $statusSlug]);
        $user = User::factory()->create(['status_id' => $status->id]);
        MemberDetail::factory()->create(['user_id' => $user->id, 'date_of_birth' => $dob]);

        return $user->fresh();
    }

    /** @return array<string, mixed> */
    private function calc(User $user): array
    {
        return app(FeeCalculationService::class)->calculate($user, '2027');
    }

    public function test_a_twelve_year_old_pays_half_the_club_fee_plus_the_jeune_licence(): void
    {
        $r = $this->calc($this->member('membre_de_droit', 120, '2014-01-15'));

        $this->assertSame(60.0, $r['components']['membership']);
        $this->assertSame(50, $r['components']['minor_pct']);
        $this->assertSame(31.5, $r['components']['lic_jeune']);
        $this->assertSame(91.5, $r['amount_due']);
    }

    public function test_a_child_under_twelve_pays_half_the_club_fee_plus_the_enfant_licence(): void
    {
        $r = $this->calc($this->member('membre_de_droit', 120, '2018-03-01'));

        $this->assertSame(60.0, $r['components']['membership']);
        $this->assertSame(14.5, $r['components']['lic_enfant']);
    }

    public function test_a_seventeen_year_old_pays_half_the_club_fee_with_the_adult_licence(): void
    {
        $r = $this->calc($this->member('membre_de_droit', 120, '2009-06-01'));

        $this->assertSame(60.0, $r['components']['membership']);
        $this->assertSame(50.0, $r['components']['lic_adulte']);
    }

    public function test_someone_who_turns_eighteen_before_the_licence_date_pays_full(): void
    {
        $r = $this->calc($this->member('membre_de_droit', 120, '2008-08-31'));

        $this->assertSame(120.0, $r['components']['membership']);
        $this->assertArrayNotHasKey('minor_pct', $r['components']);
    }

    public function test_an_unknown_date_of_birth_is_treated_as_an_adult(): void
    {
        $status = MemberStatus::firstOrCreate(['slug' => 'membre_de_droit'], ['name' => 'droit']);
        MembershipFee::create(['season_year' => '2027', 'status_id' => $status->id, 'amount' => 120, 'label' => 'droit']);
        $user = User::factory()->create(['status_id' => $status->id]);
        MemberDetail::factory()->create(['user_id' => $user->id, 'date_of_birth' => null]);

        $this->assertSame(120.0, $this->calc($user->fresh())['components']['membership']);
    }

    public function test_it_applies_to_externe_and_rounds_an_odd_amount_up(): void
    {
        $externe = $this->calc($this->member('externe', 130, '2012-01-01'));
        $odd = $this->calc($this->member('honoraire', 105, '2012-01-01'));

        $this->assertSame(65.0, $externe['components']['membership']);
        $this->assertSame(53.0, $odd['components']['membership']);
    }

    public function test_junior_and_enfant_statuses_are_not_reduced_a_second_time(): void
    {
        $r = $this->calc($this->member('junior', 55, '2012-01-01'));

        $this->assertSame(55.0, $r['components']['membership']);
    }

    public function test_an_associe_child_gets_half_of_the_membre_de_droit_fee(): void
    {
        MembershipFee::create(['season_year' => '2027', 'status_id' => MemberStatus::firstOrCreate(['slug' => 'membre_de_droit'], ['name' => 'droit'])->id, 'amount' => 120, 'label' => 'droit']);
        $associe = MemberStatus::firstOrCreate(['slug' => 'associe'], ['name' => 'associe']);
        $user = User::factory()->create(['status_id' => $associe->id]);
        MemberDetail::factory()->create(['user_id' => $user->id, 'date_of_birth' => '2014-01-15']);

        $this->assertSame(60.0, $this->calc($user->fresh())['components']['membership']);
    }

    public function test_the_season_can_change_the_age_and_percentage(): void
    {
        Season::where('year', '2027')->update(['minor_fee_below_age' => 16, 'minor_fee_percent' => 40]);
        $child = $this->calc($this->member('membre_de_droit', 120, '2014-01-15'));
        $teen = $this->calc($this->member('membre_de_droit', 120, '2009-06-01'));

        $this->assertSame(48.0, $child['components']['membership']);
        $this->assertSame(120.0, $teen['components']['membership']);
    }

    public function test_the_breakdown_explains_the_reduction(): void
    {
        $user = $this->member('membre_de_droit', 120, '2014-01-15');

        $labels = collect(app(FeeCalculationService::class)->breakdown($user, '2027'))->pluck('label')->implode(' | ');

        $this->assertStringContainsString('Under 18', $labels);
    }

    public function test_bureau_can_set_the_rule_on_the_season_page(): void
    {
        $season = Season::where('year', '2027')->first();

        $this->actingAs($this->createBureauUser())->post(route('admin.seasons.minor-fee.update', $season), [
            'minor_fee_below_age' => 18, 'minor_fee_percent' => 50,
        ])->assertRedirect();
        $this->actingAs($this->createBureauUser())->post(route('admin.seasons.minor-fee.update', $season), [
            'minor_fee_below_age' => 18, 'minor_fee_percent' => 150,
        ])->assertSessionHasErrors('minor_fee_percent');

        $this->assertSame(50, $season->fresh()->minor_fee_percent);
    }
}
