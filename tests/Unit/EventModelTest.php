<?php

namespace Tests\Unit;

use App\Models\Event;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('p1')]
class EventModelTest extends TestCase
{
    public function test_type_color_returns_custom_hex_when_set(): void
    {
        $event = new Event(['color_hex' => '#ff0000', 'event_type' => 'pool']);

        $this->assertSame('#ff0000', $event->typeColor());
    }

    /**
     * typeColor() resolves from config/activity_types.php so the calendar,
     * the badges and the instructor-planner legend agree.
     *
     * @return array<string, array{string}>
     */
    public static function configuredTypes(): array
    {
        return [
            'pool' => ['pool'],
            'training' => ['training'],
            'apnea' => ['apnea'],
            'theory' => ['theory'],
            'social' => ['social'],
            'quarry' => ['quarry'],
            'long_trip' => ['long_trip'],
        ];
    }

    #[DataProvider('configuredTypes')]
    public function test_type_color_matches_activity_types_config(string $type): void
    {
        $event = new Event(['event_type' => $type]);

        $this->assertSame(config("activity_types.{$type}.color"), $event->typeColor());
    }

    public function test_type_color_legacy_dive_matches_planning_scss(): void
    {
        $event = new Event(['event_type' => 'dive']);

        $this->assertSame('#00695c', $event->typeColor());
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

    public function test_maps_url_is_a_plain_link_even_when_a_key_is_set(): void
    {
        config(['club.google_maps_key' => 'test-key']);
        $event = new Event(['location' => 'Merl']);

        $this->assertStringContainsString('google.com/maps/search/', $event->mapsUrl());
        $this->assertStringNotContainsString('maps/embed', $event->mapsUrl());
    }

    public function test_maps_embed_url_only_present_with_key_and_location(): void
    {
        config(['club.google_maps_key' => '']);
        $this->assertSame('', (new Event(['location' => 'Merl']))->mapsEmbedUrl());

        config(['club.google_maps_key' => 'test-key']);
        $this->assertSame('', (new Event(['location' => null]))->mapsEmbedUrl());

        $url = (new Event(['location' => 'Merl']))->mapsEmbedUrl();
        $this->assertStringContainsString('maps/embed/v1/search', $url);
        $this->assertStringContainsString('key=test-key', $url);
    }
}
