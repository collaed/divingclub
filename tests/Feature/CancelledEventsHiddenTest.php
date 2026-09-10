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

    public function test_cancelled_events_stay_on_the_calendar_for_members(): void
    {
        Event::factory()->create(['title' => 'LivePoolQQ', 'status' => 'scheduled', 'event_date' => now()]);
        Event::factory()->create(['title' => 'ScrappedApneaQQ', 'status' => 'cancelled', 'event_date' => now()]);

        $member = $this->createMemberUser();

        $this->actingAs($member)->get(route('events.index'))
            ->assertOk()->assertSee('LivePoolQQ')->assertSee('ScrappedApneaQQ')
            ->assertSee('Cancelled'); // red badge beside the pill on the month grid

        $this->actingAs($member)->get(route('events.index', ['view' => 'week']))
            ->assertOk()->assertSee('ScrappedApneaQQ')->assertSee('Cancelled');
    }

    public function test_only_bureau_gets_the_restore_action_on_a_cancelled_event(): void
    {
        Event::factory()->create(['title' => 'ScrappedApneaWW', 'status' => 'cancelled', 'event_date' => now()]);

        $this->actingAs($this->createMemberUser())->get(route('events.index', ['view' => 'week']))
            ->assertOk()->assertSee('ScrappedApneaWW')->assertDontSee('uncancel', false);

        $this->actingAs($this->createBureauUser())->get(route('events.index', ['view' => 'week']))
            ->assertOk()->assertSee('uncancel', false)->assertSee('Restore');
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
