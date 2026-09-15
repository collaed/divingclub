<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

#[Group('p1')]
class DashboardBirthdaysTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_birthdays_next_2_weeks_only_counts_active_members(): void
    {
        $birthday = now()->addDays(5);

        $active = $this->createMemberUser();
        $active->detail->update(['date_of_birth' => $birthday->copy()->subYears(30), 'cotisation_years' => [(string) now()->year]]);

        $lapsed = $this->createMemberUser();
        $lapsed->detail->update(['date_of_birth' => $birthday->copy()->subYears(25)]);

        $bureau = $this->createBureauUser();

        $worklist = $this->actingAs($bureau)->get('/admin/dashboard')->assertOk()->viewData('worklist');

        $this->assertTrue($worklist['birthdays_14d']->contains('user_id', $active->id));
        $this->assertFalse($worklist['birthdays_14d']->contains('user_id', $lapsed->id));
    }
}
