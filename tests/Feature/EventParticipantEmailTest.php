<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\ThemeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * A new event's participant_email must be generated through
 * MailAliasService::eventMailto() — the same helper every other alias
 * address in the app goes through — so it respects the admin-configured
 * mailbox name and bare-vs-plus-addressing setting (ThemeSetting
 * mail_inbound_address / mail_bare_aliases), instead of a hardcoded
 * "event-{id}@{domain}" string that ignores both.
 */
class EventParticipantEmailTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_participant_email_uses_the_configured_mailbox_and_plus_addressing(): void
    {
        ThemeSetting::set('mail_inbound_address', 'cep@example.com');
        ThemeSetting::set('mail_bare_aliases', '0');

        $response = $this->actingAs($this->createBureauUser())->post(route('events.store'), [
            'title' => 'Sortie test', 'event_type' => 'dive', 'event_date' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $event = Event::where('title', 'Sortie test')->first();
        $this->assertSame("cep+event.{$event->id}@example.com", $event->participant_email);
    }

    public function test_participant_email_uses_the_bare_form_when_configured(): void
    {
        ThemeSetting::set('mail_inbound_address', 'cep@example.com');
        ThemeSetting::set('mail_bare_aliases', '1');

        $response = $this->actingAs($this->createBureauUser())->post(route('events.store'), [
            'title' => 'Sortie test', 'event_type' => 'dive', 'event_date' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $event = Event::where('title', 'Sortie test')->first();
        $this->assertSame("event-{$event->id}@example.com", $event->participant_email);
    }
}
