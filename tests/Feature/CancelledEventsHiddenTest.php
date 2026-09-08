<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

#[Group('p1')]
class CancelledEventsHiddenTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        SpatieRole::findOrCreate('instructor_apnea', 'web');
    }

    public function test_not_cancelled_scope_excludes_cancelled_events(): void
    {
        Event::factory()->create(['title' => 'LiveSession', 'status' => 'scheduled']);
        Event::factory()->create(['title' => 'ScrappedSession', 'status' => 'cancelled']);

        $titles = Event::notCancelled()->pluck('title');

        $this->assertContains('LiveSession', $titles);
        $this->assertNotContains('ScrappedSession', $titles);
    }

    public function test_admin_dashboard_upcoming_widget_hides_cancelled(): void
    {
        Event::factory()->create(['title' => 'PoolNightZZ', 'status' => 'scheduled', 'event_date' => now()->addDays(2)]);
        Event::factory()->create(['title' => 'ScrappedDiveZZ', 'status' => 'cancelled', 'event_date' => now()->addDays(2)]);

        $this->actingAs($this->createBureauUser())->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('PoolNightZZ')
            ->assertDontSee('ScrappedDiveZZ');
    }

    public function test_instructor_planning_hides_cancelled(): void
    {
        $day = now()->startOfMonth()->addDay();
        Event::factory()->create(['title' => 'MerlPoolZZ', 'status' => 'scheduled', 'event_date' => $day]);
        Event::factory()->create(['title' => 'ScrappedApneaZZ', 'status' => 'cancelled', 'event_date' => $day]);

        $this->actingAs($this->createBureauUser())->get('/availability')
            ->assertOk()
            ->assertSee('MerlPoolZZ')
            ->assertDontSee('ScrappedApneaZZ');
    }
}
