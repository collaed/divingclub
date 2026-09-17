<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * event_date/event_time and the "Registration opens/closes" fields are
 * plain, timezone-less inputs always entered as club-local wall-clock time
 * (a datetime-local input carries no timezone of its own) — the controller
 * must convert to the correct UTC instant before storing, not persist the
 * raw digits as if they were already UTC. See Event::parseClubLocalToUtc().
 */
#[Group('p1')]
class EventTimezoneTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config(['club.timezone' => 'Europe/Luxembourg']);
    }

    public function test_registration_close_time_is_stored_as_the_correct_utc_instant(): void
    {
        $response = $this->actingAs($this->createBureauUser())->post(route('events.store'), [
            'title' => 'Entraînement',
            'event_type' => 'training',
            'event_date' => now()->addDays(3)->format('Y-m-d'),
            'inscription_close_at' => '2026-09-17T16:30',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('events', ['title' => 'Entraînement', 'inscription_close_at' => '2026-09-17 14:30:00']);
    }

    public function test_registration_open_time_is_stored_as_the_correct_utc_instant(): void
    {
        $response = $this->actingAs($this->createBureauUser())->post(route('events.store'), [
            'title' => 'Entraînement',
            'event_type' => 'training',
            'event_date' => now()->addDays(3)->format('Y-m-d'),
            'inscription_open_at' => '2026-09-01T09:00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('events', ['title' => 'Entraînement', 'inscription_open_at' => '2026-09-01 07:00:00']);
    }

    public function test_omitting_registration_times_leaves_them_null(): void
    {
        $response = $this->actingAs($this->createBureauUser())->post(route('events.store'), [
            'title' => 'Entraînement',
            'event_type' => 'training',
            'event_date' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('events', ['title' => 'Entraînement', 'inscription_close_at' => null, 'inscription_open_at' => null]);
    }
}
