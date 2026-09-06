<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\Season;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Services\FeeCalculationService;
use Database\Seeders\Fee2027Seeder;
use Database\Seeders\MemberStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * The four worked examples from
 * .kiro/specs/membership-dues-calculation/requirements.md §7, at 100% taper,
 * plus the assurance gate and the FLASSA three-state, computed through the real
 * FeeCalculationService against the seeded 2027 tariff.
 */
class DuesWorkedExamplesTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private FeeCalculationService $fees;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(MemberStatusSeeder::class);
        // Season 2027 with no taper tiers => 100%.
        Season::factory()->create(['year' => '2027', 'start_date' => '2026-09-01', 'fee_taper_tiers' => null]);
        $this->seed(Fee2027Seeder::class);
        $this->fees = app(FeeCalculationService::class);
    }

    private function member(string $statusSlug, int $ageAtAnchor): User
    {
        $status = MemberStatus::where('slug', $statusSlug)->firstOrFail();
        $user = User::factory()->create(['status_id' => $status->id]);
        MemberDetail::create([
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'Member',
            'date_of_birth' => Carbon::createFromDate(2026, 9, 1)->subYears($ageAtAnchor)->toDateString(),
        ]);

        return $user->fresh(['detail', 'status']);
    }

    public function test_fonctionnaire_adult_with_loisir1top_is_228(): void
    {
        $user = $this->member('fonctionnaire', 45);
        $calc = $this->fees->calculate($user, '2027', ['ass_loisir1top']);
        // 120 + 50 + 10 + 48 = 228.00
        $this->assertSame(228.00, $calc['amount_due']);
    }

    public function test_jeune_12_15_with_loisir1_is_111_50(): void
    {
        $user = $this->member('junior', 13);
        $calc = $this->fees->calculate($user, '2027', ['ass_loisir1']);
        // 55 + 31.50 + 0 (FLASSA included) + 25 = 111.50
        $this->assertSame(111.50, $calc['amount_due']);
        $this->assertSame('included_free', $calc['components']['flassa_state']);
        $this->assertSame('lic_jeune', $calc['components']['ffessm_licence']);
    }

    public function test_jeune_16_17_no_assurance_is_105(): void
    {
        $user = $this->member('junior', 16);
        $calc = $this->fees->calculate($user, '2027', []);
        // 55 + 50 (adult licence) + 0 (FLASSA included, still <18) = 105.00
        $this->assertSame(105.00, $calc['amount_due']);
        $this->assertSame('lic_adulte', $calc['components']['ffessm_licence']);
        $this->assertSame('included_free', $calc['components']['flassa_state']);
    }

    public function test_sympathisant_is_30_and_excludes_licences_and_assurance(): void
    {
        $user = $this->member('sympathisant', 50);
        // Even if an assurance is selected, it must be dropped (R2/R3).
        $calc = $this->fees->calculate($user, '2027', ['ass_loisir3top']);
        $this->assertSame(30.00, $calc['amount_due']);
        $this->assertSame('lic_aucune', $calc['components']['ffessm_licence']);
        $this->assertSame('not_applicable', $calc['components']['flassa_state']);
        $this->assertArrayNotHasKey('ass_loisir3top', $calc['components']);
    }

    public function test_child_under_12_gets_enfant_licence_flassa_included(): void
    {
        $user = $this->member('enfant', 9);
        $calc = $this->fees->calculate($user, '2027', []);
        // 55 + 14.50 + 0 = 69.50
        $this->assertSame(69.50, $calc['amount_due']);
        $this->assertSame('lic_enfant', $calc['components']['ffessm_licence']);
        $this->assertSame('included_free', $calc['components']['flassa_state']);
    }

    public function test_adult_flassa_is_charged_ten_euro(): void
    {
        $user = $this->member('externe', 30);
        $calc = $this->fees->calculate($user, '2027', []);
        // 130 + 50 + 10 = 190.00
        $this->assertSame(190.00, $calc['amount_due']);
        $this->assertSame('required', $calc['components']['flassa_state']);
    }

    public function test_taper_reduces_only_the_cotisation_base(): void
    {
        // A tier from 01-01 at 50% that has already elapsed relative to today's
        // pinned reference; licences stay full (only the base tapers).
        $season = Season::where('year', '2027')->firstOrFail();
        $season->update(['fee_taper_tiers' => [['from' => '01-01', 'pct' => 50]]]);

        $user = $this->member('externe', 30);
        $calc = $this->fees->calculate($user, '2027', []);
        // ceil(130*0.5)=65 base + 50 FFESSM + 10 FLASSA = 125.00
        $this->assertSame(125.00, $calc['amount_due']);
        $this->assertSame(50, $calc['components']['taper_pct']);
    }

    public function test_grace_days_shift_the_effective_cutoff(): void
    {
        $season = Season::where('year', '2027')->firstOrFail();
        // Tier begins tomorrow; with 0 grace we are before it (100%).
        $tomorrow = Carbon::today()->addDay();
        $season->update(['fee_taper_tiers' => [['from' => $tomorrow->format('m-d'), 'pct' => 40]]]);

        ThemeSetting::set('dues_cutoff_grace_days', '0');
        $this->assertSame(100, $this->fees->taperPercentage('2027'));

        // With 2 grace days, as_of_date crosses the tier => 40%.
        ThemeSetting::set('dues_cutoff_grace_days', '2');
        $this->assertSame(40, $this->fees->taperPercentage('2027'));
    }
}
