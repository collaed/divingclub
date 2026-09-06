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
        $user = $this->memberWithAge('junior', 13);

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

    public function test_age_status_mismatch_is_rejected(): void
    {
        // A member born 40 years ago cannot claim the "enfant" (<12) cotisation.
        $user = $this->memberWithAge('externe', 40);
        $enfant = MemberStatus::where('slug', 'enfant')->firstOrFail();

        $this->actingAs($user)->post(route('dues.calculate'), [
            'season_year' => '2027',
            'status_id' => $enfant->id,
            'date_of_birth' => Carbon::createFromDate(2026, 9, 1)->subYears(40)->toDateString(),
        ])->assertSessionHasErrors('status_id');
    }
}
