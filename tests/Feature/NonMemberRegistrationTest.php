<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

#[Group('p0')]
class NonMemberRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::upsert([['id' => 2, 'name' => 'Member', 'slug' => 'member'], ['id' => 6, 'name' => 'Bureau Master', 'slug' => 'bureau_master']], ['id']);
        SpatieRole::findOrCreate('member', 'web');
        SpatieRole::findOrCreate('bureau_master', 'web');
        MemberStatus::upsert([['id' => 1, 'name' => 'Active', 'slug' => 'active']], ['id']);
    }

    private function bureau(): User
    {
        $u = User::create(['primary_email' => fake()->unique()->safeEmail(), 'password' => 'P', 'role_id' => 6, 'status_id' => 1, 'email_verified_at' => now()]);
        $u->assignRole('bureau_master');
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'Admin', 'last_name' => 'Boss', 'sex' => 'M', 'phone_mobile' => '+352 621 000 000', 'date_of_birth' => '1980-01-01', 'emergency_contact_name' => 'EC', 'emergency_contact_phone' => '+352 621 000 001']);

        return $u;
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
        $creator = User::first() ?? $this->bureau();

        return Event::create(array_merge([
            'title' => 'Juan-les-Pins', 'event_type' => 'social', 'event_date' => now()->addDays(7),
            'status' => 'scheduled', 'created_by' => $creator->id,
        ], $attrs));
    }

    public function test_bureau_can_register_non_member(): void
    {
        $bureau = $this->bureau();
        $event = $this->event();

        $response = $this->actingAs($bureau)->post("/events/{$event->id}/register", [
            'non_member_name' => 'Bruno Baumlen',
            'comment' => 'accompagnant',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $event->id,
            'user_id' => null,
            'non_member_name' => 'Bruno Baumlen',
            'status' => 'confirmed',
            'comment' => 'accompagnant',
            'registered_by' => $bureau->id,
        ]);
    }

    public function test_regular_member_cannot_register_non_member(): void
    {
        $member = $this->member();
        $event = $this->event();

        $response = $this->actingAs($member)->post("/events/{$event->id}/register", [
            'non_member_name' => 'Random Person',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('event_registrations', ['non_member_name' => 'Random Person']);
    }

    public function test_duplicate_non_member_rejected(): void
    {
        $bureau = $this->bureau();
        $event = $this->event();

        $this->actingAs($bureau)->post("/events/{$event->id}/register", [
            'non_member_name' => 'Bruno Baumlen',
        ]);

        $response = $this->actingAs($bureau)->post("/events/{$event->id}/register", [
            'non_member_name' => 'Bruno Baumlen',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(1, EventRegistration::where('event_id', $event->id)->where('non_member_name', 'Bruno Baumlen')->count());
    }

    public function test_bureau_can_cancel_non_member_registration(): void
    {
        $bureau = $this->bureau();
        $event = $this->event();

        $this->actingAs($bureau)->post("/events/{$event->id}/register", [
            'non_member_name' => 'Bruno Baumlen',
        ]);

        $reg = EventRegistration::where('non_member_name', 'Bruno Baumlen')->first();

        $response = $this->actingAs($bureau)->post("/events/{$event->id}/cancel-registration", [
            'registration_id' => $reg->id,
            'cancel_comment' => 'No longer attending',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('event_registrations', [
            'id' => $reg->id,
            'status' => 'cancelled',
            'cancel_comment' => 'No longer attending',
        ]);
    }

    public function test_non_member_counted_in_participants(): void
    {
        $bureau = $this->bureau();
        $event = $this->event(['max_participants' => 2, 'waiting_list_enabled' => true]);
        $member = $this->member();

        // Register member and non-member (fills 2 slots)
        $this->actingAs($bureau)->post("/events/{$event->id}/register", ['user_id' => $member->id]);
        $this->actingAs($bureau)->post("/events/{$event->id}/register", ['non_member_name' => 'Companion']);

        // Third person should go to waiting list
        $member2 = $this->member();
        $this->actingAs($member2)->post("/events/{$event->id}/register");

        $this->assertDatabaseHas('event_registrations', ['user_id' => $member2->id, 'status' => 'waiting']);
    }

    public function test_participant_name_helper(): void
    {
        $bureau = $this->bureau();
        $event = $this->event();
        $member = $this->member();

        // Member registration
        EventRegistration::create(['event_id' => $event->id, 'user_id' => $member->id, 'status' => 'confirmed']);
        // Non-member registration
        EventRegistration::create(['event_id' => $event->id, 'user_id' => null, 'non_member_name' => 'Bruno Baumlen', 'status' => 'confirmed', 'registered_by' => $bureau->id]);

        $memberReg = EventRegistration::where('user_id', $member->id)->first();
        $nonMemberReg = EventRegistration::where('non_member_name', 'Bruno Baumlen')->first();

        $this->assertEquals($member->name, $memberReg->participantName());
        $this->assertEquals('Bruno Baumlen', $nonMemberReg->participantName());
        $this->assertFalse($memberReg->isNonMember());
        $this->assertTrue($nonMemberReg->isNonMember());
    }
}
