<?php

namespace Tests\Unit;

use App\Models\Event;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * @group p1
 */
class EventModelTest extends TestCase
{
    public function test_type_color_returns_custom_hex_when_set(): void
    {
        $event = new Event(['color_hex' => '#ff0000', 'event_type' => 'pool']);

        $this->assertSame('#ff0000', $event->typeColor());
    }

    public function test_type_color_pool(): void
    {
        $event = new Event(['event_type' => 'pool']);

        $this->assertSame('#0077be', $event->typeColor());
    }

    public function test_type_color_dive(): void
    {
        $event = new Event(['event_type' => 'dive']);

        $this->assertSame('#003366', $event->typeColor());
    }

    public function test_type_color_training(): void
    {
        $event = new Event(['event_type' => 'training']);

        $this->assertSame('#28a745', $event->typeColor());
    }

    public function test_type_color_theory(): void
    {
        $event = new Event(['event_type' => 'theory']);

        $this->assertSame('#6f42c1', $event->typeColor());
    }

    public function test_type_color_social(): void
    {
        $event = new Event(['event_type' => 'social']);

        $this->assertSame('#ffc107', $event->typeColor());
    }

    public function test_type_color_unknown_defaults_to_grey(): void
    {
        $event = new Event(['event_type' => 'unknown']);

        $this->assertSame('#6c757d', $event->typeColor());
    }

    public function test_registration_closed_when_inscriptions_closed(): void
    {
        $event = new Event(['inscriptions_closed' => true, 'status' => 'scheduled']);

        $this->assertFalse($event->isRegistrationOpen());
    }

    public function test_registration_closed_when_cancelled(): void
    {
        $event = new Event(['inscriptions_closed' => false, 'status' => 'cancelled']);

        $this->assertFalse($event->isRegistrationOpen());
    }

    public function test_registration_closed_when_open_at_is_future(): void
    {
        $event = new Event([
            'inscriptions_closed' => false,
            'status' => 'scheduled',
            'inscription_open_at' => Carbon::tomorrow(),
        ]);

        $this->assertFalse($event->isRegistrationOpen());
    }

    public function test_registration_open_when_scheduled_and_not_closed(): void
    {
        $event = new Event([
            'inscriptions_closed' => false,
            'status' => 'scheduled',
            'inscription_open_at' => null,
        ]);

        $this->assertTrue($event->isRegistrationOpen());
    }

    public function test_maps_url_empty_when_no_location(): void
    {
        $event = new Event(['location' => null]);

        $this->assertSame('', $event->mapsUrl());
    }

    public function test_maps_url_uses_google_search_fallback(): void
    {
        $event = new Event(['location' => 'Remerschen Quarry']);

        $url = $event->mapsUrl();

        $this->assertStringContainsString('google.com/maps/search/', $url);
        $this->assertStringContainsString('Remerschen', $url);
    }
}
