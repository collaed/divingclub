<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class AdminTrashTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_trash_lists_soft_deleted_records_and_restores_them(): void
    {
        $bureau = $this->createBureauUser();
        $event = Event::factory()->create(['title' => 'TrashedApneaNight']);
        $event->delete();

        $this->assertSoftDeleted('events', ['id' => $event->id]);

        $this->actingAs($bureau)->get(route('admin.trash.index', ['kind' => 'events']))
            ->assertOk()
            ->assertSee('TrashedApneaNight');

        $this->actingAs($bureau)
            ->post(route('admin.trash.restore', ['events', $event->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'deleted_at' => null]);
    }

    public function test_cancelled_events_tab_lists_and_uncancels(): void
    {
        $bureau = $this->createBureauUser();
        $event = Event::factory()->create(['title' => 'ScrappedNightDive', 'status' => 'cancelled']);

        $this->actingAs($bureau)->get(route('admin.trash.index', ['kind' => 'cancelled-events']))
            ->assertOk()
            ->assertSee('ScrappedNightDive');

        $this->actingAs($bureau)
            ->post(route('admin.trash.cancelled-event.restore', $event))
            ->assertRedirect();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'scheduled', 'deleted_at' => null]);
    }

    public function test_binning_a_cancelled_event_soft_deletes_it(): void
    {
        $bureau = $this->createBureauUser();
        $event = Event::factory()->create(['status' => 'cancelled']);

        $this->actingAs($bureau)
            ->delete(route('admin.trash.cancelled-event.trash', $event))
            ->assertRedirect();

        $this->assertSoftDeleted('events', ['id' => $event->id]);
    }

    public function test_permanent_delete_is_bureau_master_only(): void
    {
        $event = Event::factory()->create();
        $event->delete();

        // A non-master bureau user cannot force-delete.
        $finance = $this->createBureauUser();
        $finance->syncRoles(['bureau_finance']);

        $this->actingAs($finance)
            ->delete(route('admin.trash.force-delete', ['events', $event->id]))
            ->assertForbidden();

        $this->assertSoftDeleted('events', ['id' => $event->id]);
    }
}
