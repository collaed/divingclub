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

    public function test_upcoming_birthdays_only_counts_active_members(): void
    {
        $birthday = now()->addDays(5);

        $active = $this->createMemberUser();
        $active->detail->update(['date_of_birth' => $birthday->copy()->subYears(30), 'cotisation_years' => [(string) now()->year]]);

        $lapsed = $this->createMemberUser();
        $lapsed->detail->update(['date_of_birth' => $birthday->copy()->subYears(25)]);

        $bureau = $this->createBureauUser();

        $stats = $this->actingAs($bureau)->get('/admin/dashboard')->assertOk()->viewData('stats');

        $this->assertTrue($stats['upcoming_birthdays']->contains('user_id', $active->id));
        $this->assertFalse($stats['upcoming_birthdays']->contains('user_id', $lapsed->id));
    }
}
