<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Recap of recent event audit-log changes shown at the bottom of the
 * calendar page — bureau/instructors only (see EventController::index()).
 */
#[Group('p1')]
class EventChangeRecapTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_a_member_does_not_see_the_recap(): void
    {
        $bureau = $this->createBureauUser();
        $this->actingAs($bureau);
        $event = Event::factory()->create(['title' => 'FosseRecapAA']);
        $event->update(['title' => 'FosseRecapAA-Renamed']);

        $this->actingAs($this->createMemberUser())->get(route('events.index'))
            ->assertOk()
            ->assertDontSee('Recent event changes');
    }

    public function test_bureau_sees_who_changed_an_event_and_what_changed(): void
    {
        $bureau = $this->createBureauUser();
        $this->actingAs($bureau);
        $event = Event::factory()->create(['title' => 'FosseRecapBB']);
        $event->update(['title' => 'FosseRecapBB-Renamed', 'event_date' => now()->addDay()]);

        $this->actingAs($bureau)->get(route('events.index'))
            ->assertOk()
            ->assertSee('Recent event changes')
            ->assertSee('FosseRecapBB-Renamed')
            ->assertSee($bureau->name)
            ->assertSee('title');
    }

    public function test_an_instructor_sees_the_recap_too(): void
    {
        Role::findOrCreate('instructor', 'web');
        $bureau = $this->createBureauUser();
        $this->actingAs($bureau);
        $event = Event::factory()->create(['title' => 'FosseRecapCC']);
        $event->update(['title' => 'FosseRecapCC-Renamed']);

        $instructor = $this->createMemberUser();
        $instructor->assignRole('instructor');

        $this->actingAs($instructor)->get(route('events.index'))
            ->assertOk()
            ->assertSee('Recent event changes')
            ->assertSee('FosseRecapCC-Renamed');
    }

    public function test_a_deleted_events_change_still_shows_in_the_recap(): void
    {
        $bureau = $this->createBureauUser();
        $this->actingAs($bureau);
        $event = Event::factory()->create(['title' => 'FosseRecapDD']);
        $event->delete();

        $this->actingAs($bureau)->get(route('events.index'))
            ->assertOk()
            ->assertSee('Recent event changes')
            ->assertSee('FosseRecapDD');
    }
}
