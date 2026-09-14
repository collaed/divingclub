<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

#[Group('p1')]
class DashboardScheduledTasksTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_suspended_legacy_syncs_show_as_suspended_not_overdue(): void
    {
        // Stale by any task's stale_hours threshold — without the suspended
        // flag this would render "Overdue".
        foreach (['joomla-sync', 'legacy-sync-bidi'] as $task) {
            DB::table('schedule_heartbeats')->insert([
                'task' => $task, 'last_run_at' => now()->subYear(), 'success' => true,
            ]);
        }

        $response = $this->actingAs($this->createBureauUser())->get('/admin/dashboard')->assertOk();

        $response->assertSeeText('Suspended');
        $response->assertDontSeeText('Overdue');
    }
}
