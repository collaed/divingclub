<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\Season;
use App\Models\User;
use Database\Seeders\Fee2027Seeder;
use Database\Seeders\MemberStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class DuesCalculatorControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(MemberStatusSeeder::class);
        Season::factory()->create(['year' => '2027', 'start_date' => '2026-09-01', 'fee_taper_tiers' => null]);
        $this->seed(Fee2027Seeder::class);
    }

    private function memberWithAge(string $statusSlug, int $age): User
    {
        $status = MemberStatus::where('slug', $statusSlug)->firstOrFail();
        $user = User::factory()->create(['status_id' => $status->id, 'email_verified_at' => now()]);
        MemberDetail::create([
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'Member',
            'date_of_birth' => Carbon::createFromDate(2026, 9, 1)->subYears($age)->toDateString(),
        ]);

        return $user->fresh(['detail', 'status']);
    }

    public function test_adult_calculation_shows_derived_adult_licence_and_flassa(): void
    {
        $user = $this->memberWithAge('externe', 30);

        $this->actingAs($user)->post(route('dues.calculate'), [
            'season_year' => '2027',
            'status_id' => $user->status_id,
            'last_name' => 'Member',
            'first_name' => 'Test',
        ])->assertOk()
            ->assertSee('190.00'); // 130 + 50 + 10
    }

    public function test_minor_calculation_shows_flassa_included(): void
    {
        $user = $this->memberWithAge('externe', 13);

        $this->actingAs($user)->post(route('dues.calculate'), [
            'season_year' => '2027',
            'status_id' => $user->status_id,
        ])->assertOk()
            ->assertSee('included_free', false); // flassa_state present in components
    }

    public function test_sympathisant_calculation_excludes_licences(): void
    {
        $user = $this->memberWithAge('sympathisant', 40);

        $this->actingAs($user)->post(route('dues.calculate'), [
            'season_year' => '2027',
            'status_id' => $user->status_id,
        ])->assertOk()
            ->assertSee('30.00');
    }

    public function test_a_child_can_be_calculated_as_externe_and_gets_the_under_18_share(): void
    {
        $user = $this->memberWithAge('externe', 10);

        $this->actingAs($user)->post(route('dues.calculate'), [
            'season_year' => '2027',
            'status_id' => $user->status_id,
        ])->assertOk()
            ->assertSessionHasNoErrors()
            ->assertSee('79.50') // 65 (half of 130) + 14.50 enfant licence + FLASSA included
            ->assertSee('Under 18');
    }

    public function test_the_calculator_page_explains_the_under_18_rule(): void
    {
        $this->actingAs($this->memberWithAge('externe', 30))->get(route('dues.show'))
            ->assertOk()
            ->assertSee('50% of the club cotisation');
    }

    /**
     * The real-world cases for 2027: a Membre de droit (Fonctionnaire, Associé,
     * Famille, ...) pays 120, an Externe 130; under 18 at 1 Sept they pay half
     * (60 / 65). FFESSM licence by age: under 12 = 14.50, 12 to 15 = 31.50,
     * 16 and over = 50. FLASSA (10) is only charged from 18.
     *
     * @return array<string, array{string, int, float}>
     */
    public static function realWorldCases(): array
    {
        return [
            'fonctionnaire adult' => ['fonctionnaire', 30, 180.00],      // 120 + 50 + 10
            'fonctionnaire 17' => ['fonctionnaire', 17, 110.00],         // 60 + 50
            'fonctionnaire 16' => ['fonctionnaire', 16, 110.00],         // 60 + 50
            'fonctionnaire 15' => ['fonctionnaire', 15, 91.50],          // 60 + 31.50
            'fonctionnaire 12' => ['fonctionnaire', 12, 91.50],          // 60 + 31.50
            'fonctionnaire 11' => ['fonctionnaire', 11, 74.50],          // 60 + 14.50
            'fonctionnaire 5' => ['fonctionnaire', 5, 74.50],
            'associe child 9' => ['associe', 9, 74.50],
            'associe teen 17' => ['associe', 17, 110.00],
            'famille adult' => ['famille', 40, 180.00],
            'externe adult' => ['externe', 30, 190.00],                  // 130 + 50 + 10
            'externe 18' => ['externe', 18, 190.00],
            'externe 17' => ['externe', 17, 115.00],                     // 65 + 50
            'externe 15' => ['externe', 15, 96.50],                      // 65 + 31.50
            'externe 10' => ['externe', 10, 79.50],                      // 65 + 14.50
        ];
    }

    #[DataProvider('realWorldCases')]
    public function test_real_world_dues_by_status_and_age(string $status, int $age, float $expected): void
    {
        $user = $this->memberWithAge($status, $age);

        $res = $this->actingAs($user)->post(route('dues.calculate'), [
            'season_year' => '2027',
            'status_id' => $user->status_id,
        ])->assertOk()->assertSessionHasNoErrors();

        $res->assertSee(number_format($expected, 2));
    }

    public function test_the_system_actif_status_is_not_offered_and_cannot_be_committed(): void
    {
        $actif = MemberStatus::where('slug', 'actif')->firstOrFail();
        $user = $this->memberWithAge('externe', 30);

        $this->actingAs($user)->get(route('dues.show'))
            ->assertOk()
            ->assertSee('Externe')
            ->assertDontSee('Actif');

        $this->actingAs($user)->post(route('dues.commit'), ['season_year' => '2027', 'status_id' => $actif->id])
            ->assertSessionHasErrors('status_id');
    }
}
