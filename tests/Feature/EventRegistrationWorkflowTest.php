<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * @group p0
 */
class EventRegistrationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::upsert([['id' => 2, 'name' => 'Member', 'slug' => 'member']], ['id']);
        SpatieRole::findOrCreate('member', 'web');
        MemberStatus::upsert([['id' => 1, 'name' => 'Active', 'slug' => 'active']], ['id']);
    }

    private function member(): User
    {
        $u = User::create(['primary_email' => fake()->unique()->safeEmail(), 'password' => 'P', 'role_id' => 2, 'status_id' => 1, 'email_verified_at' => now()]);
        $u->assignRole('member');
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'T', 'last_name' => 'U', 'sex' => 'M', 'phone_mobile' => '+352 621 000 000', 'date_of_birth' => '1985-01-01', 'emergency_contact_name' => 'EC', 'emergency_contact_phone' => '+352 621 000 001']);

        return $u;
    }

    private function event(array $attrs = []): Event
    {
        $creator = User::first() ?? $this->member();

        return Event::create(array_merge([
            'title' => 'Test', 'event_type' => 'social', 'event_date' => now()->addDays(7),
            'status' => 'scheduled', 'created_by' => $creator->id,
        ], $attrs));
    }

    public function test_event_full_puts_on_waiting_list(): void
    {
        $event = $this->event(['max_participants' => 1, 'waiting_list_enabled' => true]);
        $u1 = $this->member();
        $u2 = $this->member();

        $this->actingAs($u1)->post("/events/{$event->id}/register");
        $this->actingAs($u2)->post("/events/{$event->id}/register");

        $this->assertDatabaseHas('event_registrations', ['user_id' => $u1->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('event_registrations', ['user_id' => $u2->id, 'status' => 'waiting']);
    }

    public function test_cancel_promotes_waiting_to_confirmed(): void
    {
        $event = $this->event(['max_participants' => 1, 'waiting_list_enabled' => true]);
        $u1 = $this->member();
        $u2 = $this->member();

        $this->actingAs($u1)->post("/events/{$event->id}/register");
        $this->actingAs($u2)->post("/events/{$event->id}/register");

        // u1 cancels → u2 should auto-promote
        $this->actingAs($u1)->post("/events/{$event->id}/cancel-registration");

        $this->assertDatabaseHas('event_registrations', ['user_id' => $u2->id, 'status' => 'confirmed']);
    }

    public function test_full_event_without_waitlist_rejects(): void
    {
        $event = $this->event(['max_participants' => 1, 'waiting_list_enabled' => false]);
        $u1 = $this->member();
        $u2 = $this->member();

        $this->actingAs($u1)->post("/events/{$event->id}/register");
        $this->actingAs($u2)->post("/events/{$event->id}/register")->assertSessionHas('error');

        $this->assertDatabaseMissing('event_registrations', ['user_id' => $u2->id]);
    }

    public function test_duplicate_registration_rejected(): void
    {
        $event = $this->event();
        $u = $this->member();

        $this->actingAs($u)->post("/events/{$event->id}/register");
        $this->actingAs($u)->post("/events/{$event->id}/register")->assertSessionHas('error');

        $this->assertEquals(1, EventRegistration::where('event_id', $event->id)->where('user_id', $u->id)->count());
    }

    public function test_waiting_list_preserves_order(): void
    {
        $event = $this->event(['max_participants' => 1, 'waiting_list_enabled' => true]);
        $u1 = $this->member();
        $u2 = $this->member();
        $u3 = $this->member();

        $this->actingAs($u1)->post("/events/{$event->id}/register");
        $this->actingAs($u2)->post("/events/{$event->id}/register");
        $this->actingAs($u3)->post("/events/{$event->id}/register");

        $pos2 = EventRegistration::where('user_id', $u2->id)->value('waiting_list_position');
        $pos3 = EventRegistration::where('user_id', $u3->id)->value('waiting_list_position');

        $this->assertLessThan($pos3, $pos2);
    }
}
