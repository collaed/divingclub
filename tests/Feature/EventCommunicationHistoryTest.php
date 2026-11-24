<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\Event;
use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * The event page's "Communications" card (resources/views/events/show.blade.php)
 * must list every email logged against the event, newest first — see
 * EventController::show()'s $emailHistory query.
 */
class EventCommunicationHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::upsert([['id' => 2, 'name' => 'Member', 'slug' => 'member']], ['id']);
        SpatieRole::findOrCreate('member', 'web');
        SpatieRole::findOrCreate('instructor', 'web');
        SpatieRole::findOrCreate('instructor_apnea', 'web');
        SpatieRole::findOrCreate('assistant', 'web');
        MemberStatus::upsert([['id' => 1, 'name' => 'Active', 'slug' => 'active']], ['id']);
    }

    private function member(): User
    {
        $u = User::create(['primary_email' => fake()->unique()->safeEmail(), 'password' => 'P', 'role_id' => 2, 'status_id' => 1, 'email_verified_at' => now()]);
        $u->assignRole('member');
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'T', 'last_name' => 'U', 'sex' => 'M', 'phone_mobile' => '+352 621 000 000', 'date_of_birth' => '1985-01-01', 'emergency_contact_name' => 'EC', 'emergency_contact_phone' => '+352 621 000 001']);

        return $u;
    }

    private function event(): Event
    {
        return Event::create([
            'title' => 'Test dive', 'event_type' => 'social', 'event_date' => now()->addDays(7),
            'status' => 'scheduled', 'created_by' => $this->member()->id,
        ]);
    }

    public function test_communications_are_listed_newest_first_on_the_event_page(): void
    {
        $event = $this->event();
        $member = $this->member();

        EmailLog::create([
            'event_id' => $event->id, 'to_email' => 'a@example.com', 'subject' => 'Oldest message',
            'body' => 'first', 'status' => 'forwarded', 'direction' => 'inbound',
            'created_at' => now()->subHours(2),
        ]);
        EmailLog::create([
            'event_id' => $event->id, 'to_email' => 'b@example.com', 'subject' => 'Middle message',
            'body' => 'second', 'status' => 'forwarded', 'direction' => 'inbound',
            'created_at' => now()->subHour(),
        ]);
        EmailLog::create([
            'event_id' => $event->id, 'to_email' => 'c@example.com', 'subject' => 'Newest message',
            'body' => 'third', 'status' => 'forwarded', 'direction' => 'inbound',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($member)->get("/events/{$event->id}");

        $response->assertOk();
        $html = $response->getContent();
        $posNewest = strpos($html, 'Newest message');
        $posMiddle = strpos($html, 'Middle message');
        $posOldest = strpos($html, 'Oldest message');

        $this->assertNotFalse($posNewest);
        $this->assertNotFalse($posMiddle);
        $this->assertNotFalse($posOldest);
        $this->assertTrue($posNewest < $posMiddle, 'Newest communication should render before the middle one.');
        $this->assertTrue($posMiddle < $posOldest, 'Middle communication should render before the oldest one.');
    }

    public function test_a_communication_logged_via_legacy_to_email_alias_still_shows_on_the_event_page(): void
    {
        $event = $this->event();
        $member = $this->member();

        // Some older EmailLog rows have no event_id set directly — they're
        // matched by the alias address pattern instead (see
        // EventController::show()'s $emailHistory query).
        EmailLog::create([
            'to_email' => "event-{$event->id}@example.com", 'subject' => 'Legacy alias match',
            'body' => 'legacy', 'status' => 'forwarded', 'direction' => 'inbound',
        ]);

        $response = $this->actingAs($member)->get("/events/{$event->id}");

        $response->assertOk();
        $response->assertSee('Legacy alias match');
    }

    public function test_communications_for_another_event_do_not_appear(): void
    {
        $event = $this->event();
        $otherEvent = $this->event();
        $member = $this->member();

        EmailLog::create([
            'event_id' => $otherEvent->id, 'to_email' => 'x@example.com', 'subject' => 'Not this event',
            'body' => 'other', 'status' => 'forwarded', 'direction' => 'inbound',
        ]);

        $response = $this->actingAs($member)->get("/events/{$event->id}");

        $response->assertOk();
        $response->assertDontSee('Not this event');
    }
}
