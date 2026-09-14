<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

#[Group('p1')]
class UserIsActiveTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_a_cotisation_labelled_the_current_calendar_year_is_active(): void
    {
        $year = (string) now()->year;
        $user = $this->memberWithCotisationYears([$year]);

        $this->assertTrue($user->isActive());
        $this->assertTrue(User::active()->whereKey($user->id)->exists());
    }

    public function test_a_cotisation_labelled_next_calendar_year_is_also_active(): void
    {
        // The season starting this September is labelled next calendar year
        // and is valid from the day it's paid, not just from January.
        $nextYear = (string) (now()->year + 1);
        $user = $this->memberWithCotisationYears([$nextYear]);

        $this->assertTrue($user->isActive());
        $this->assertTrue(User::active()->whereKey($user->id)->exists());
    }

    public function test_a_cotisation_only_for_a_season_that_ended_last_calendar_year_is_not_active(): void
    {
        $lastYear = (string) (now()->year - 1);
        $user = $this->memberWithCotisationYears([$lastYear]);

        $this->assertFalse($user->isActive());
        $this->assertFalse(User::active()->whereKey($user->id)->exists());
    }

    public function test_honoraire_is_always_active_even_with_no_cotisation_years(): void
    {
        $honoraire = MemberStatus::firstOrCreate(['slug' => 'honoraire'], ['name' => 'Honoraire']);
        $user = $this->memberWithCotisationYears([], $honoraire->id);

        $this->assertTrue($user->isActive());
        $this->assertTrue(User::active()->whereKey($user->id)->exists());
    }

    private function memberWithCotisationYears(array $years, ?int $statusId = null): User
    {
        $user = User::factory()->create(['status_id' => $statusId ?? 1]);
        MemberDetail::factory()->create(['user_id' => $user->id, 'cotisation_years' => $years]);

        return $user->fresh();
    }
}
