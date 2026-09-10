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
