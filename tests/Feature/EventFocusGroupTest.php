<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * focus_group is an equipment-priority marker, orthogonal to event_type —
 * see config/event_focus_groups.php and the .focus-* hatch in _planning.scss.
 */
#[Group('p1')]
class EventFocusGroupTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_a_bureau_member_can_set_a_focus_group_on_a_new_event(): void
    {
        $response = $this->actingAs($this->createBureauUser())->post(route('events.store'), [
            'title' => 'Entraînement',
            'event_type' => 'training',
            'focus_group' => 'kids',
            'event_date' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('events', ['title' => 'Entraînement', 'focus_group' => 'kids']);
    }

    public function test_focus_group_is_optional(): void
    {
        $response = $this->actingAs($this->createBureauUser())->post(route('events.store'), [
            'title' => 'Entraînement',
            'event_type' => 'training',
            'event_date' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('events', ['title' => 'Entraînement', 'focus_group' => null]);
    }

    public function test_an_unknown_focus_group_is_rejected(): void
    {
        $response = $this->actingAs($this->createBureauUser())->post(route('events.store'), [
            'title' => 'Entraînement',
            'event_type' => 'training',
            'focus_group' => 'not-a-real-group',
            'event_date' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('focus_group');
    }

    public function test_a_bureau_member_can_update_an_events_focus_group(): void
    {
        $event = Event::create(['title' => 'Entraînement', 'event_type' => 'training', 'event_date' => now()->addDays(3)]);

        $response = $this->actingAs($this->createBureauUser())->put(route('events.update', $event), [
            'title' => 'Entraînement',
            'event_type' => 'training',
            'focus_group' => 'pn1',
            'event_date' => $event->event_date->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $this->assertSame('pn1', $event->refresh()->focus_group);
    }
}
