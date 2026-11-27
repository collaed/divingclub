<?php

namespace Tests\Feature;

use App\Http\Requests\StoreEventRequest;
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
class EventRegistrationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::upsert([['id' => 2, 'name' => 'Member', 'slug' => 'member'], ['id' => 3, 'name' => 'Instructor', 'slug' => 'instructor']], ['id']);
        SpatieRole::findOrCreate('member', 'web');
        SpatieRole::findOrCreate('instructor_apnea', 'web');
        SpatieRole::findOrCreate('bureau_master', 'web');
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

    public function test_published_events_are_open_for_registration(): void
    {
        $event = $this->event(['status' => 'published']);
        $this->assertTrue($event->isRegistrationOpen());

        $u = $this->member();
        $this->actingAs($u)->post("/events/{$event->id}/register");

        $this->assertDatabaseHas('event_registrations', ['event_id' => $event->id, 'user_id' => $u->id, 'status' => 'confirmed']);
    }

    public function test_cancelled_and_completed_events_are_not_open(): void
    {
        $this->assertFalse($this->event(['status' => 'cancelled'])->isRegistrationOpen());
        $this->assertFalse($this->event(['status' => 'completed'])->isRegistrationOpen());
    }

    public function test_inscription_close_at_gates_registration(): void
    {
        $this->assertFalse($this->event(['inscription_close_at' => now()->subDay()])->isRegistrationOpen());
        $this->assertTrue($this->event(['inscription_close_at' => now()->addDays(3)])->isRegistrationOpen());
    }

    public function test_registration_mode_helpers_and_not_needed_stays_open(): void
    {
        $this->assertTrue($this->event(['registration_mode' => 'required'])->registrationRequired());
        $notNeeded = $this->event(['registration_mode' => 'not_needed']);
        $this->assertTrue($notNeeded->registrationNotNeeded());
        $this->assertTrue($notNeeded->isRegistrationOpen()); // instructors still sign up
    }

    public function test_new_registration_fields_are_mass_assignable(): void
    {
        $e = $this->event([
            'registration_mode' => 'not_needed',
            'inscription_close_at' => now()->addDays(2),
        ])->fresh();

        $this->assertSame('not_needed', $e->registration_mode);
        $this->assertNotNull($e->inscription_close_at);
    }

    public function test_store_request_accepts_the_registration_mode_values(): void
    {
        $rules = (new StoreEventRequest)->rules();
        $this->assertStringContainsString('required', $rules['registration_mode']);
        $this->assertStringContainsString('not_needed', $rules['registration_mode']);
        $this->assertArrayHasKey('inscription_close_at', $rules);
    }

    public function test_the_instructor_bucket_follows_the_live_role_not_the_stale_legacy_role_id(): void
    {
        $event = $this->event(['event_type' => 'pool']);

        // Stale legacy role_id=3 ("instructor" in legacy_roles) from a past promotion
        // that was never reverted — must not count; only the live Spatie role does.
        $demoted = $this->member();
        $demoted->update(['role_id' => 3]);

        $freedivingInstructor = $this->member();
        SpatieRole::findOrCreate('instructor_apnea', 'web');
        $freedivingInstructor->assignRole('instructor_apnea');

        $this->actingAs($demoted)->post("/events/{$event->id}/register");
        $this->actingAs($freedivingInstructor)->post("/events/{$event->id}/register");

        $response = $this->actingAs($demoted)->get("/events/{$event->id}");

        $response->assertOk();
        $response->assertSeeInOrder([__('Instructors'), $freedivingInstructor->detail->first_name, __('Members'), $demoted->detail->first_name]);
    }

    public function test_social_events_do_not_split_out_an_instructors_group(): void
    {
        $event = $this->event(['event_type' => 'social']);

        $instructor = $this->member();
        SpatieRole::findOrCreate('instructor_apnea', 'web');
        $instructor->assignRole('instructor_apnea');

        $this->actingAs($instructor)->post("/events/{$event->id}/register");

        $response = $this->actingAs($instructor)->get("/events/{$event->id}");

        $response->assertOk();
        $response->assertDontSee(__('Instructors'));
    }

    public function test_the_person_who_registered_someone_else_can_unregister_them(): void
    {
        $event = $this->event();
        $registrar = $this->member();
        $target = $this->member();

        $this->actingAs($registrar)->post("/events/{$event->id}/register", ['user_id' => $target->id]);
        $reg = EventRegistration::where('event_id', $event->id)->where('user_id', $target->id)->first();
        $this->assertSame($registrar->id, $reg->registered_by);

        $this->actingAs($registrar)->post("/events/{$event->id}/cancel-registration", ['user_id' => $target->id]);

        $this->assertDatabaseHas('event_registrations', ['id' => $reg->id, 'status' => 'cancelled', 'cancelled_by' => $registrar->id]);
    }

    public function test_a_family_member_can_unregister_a_registered_relative(): void
    {
        $event = $this->event();
        $familyMember = $this->member();
        $target = $this->member();
        $target->detail->update(['family_id' => 1]);
        $familyMember->detail->update(['family_id' => 1]);

        $this->actingAs($target)->post("/events/{$event->id}/register");
        $reg = EventRegistration::where('event_id', $event->id)->where('user_id', $target->id)->first();

        $this->actingAs($familyMember)->post("/events/{$event->id}/cancel-registration", ['user_id' => $target->id])->assertRedirect();

        $this->assertDatabaseHas('event_registrations', ['id' => $reg->id, 'status' => 'cancelled', 'cancelled_by' => $familyMember->id]);
    }

    public function test_any_instructor_can_unregister_a_participant_even_without_teaching_the_event(): void
    {
        $event = $this->event();
        $target = $this->member();
        $unrelatedInstructor = $this->member();
        SpatieRole::findOrCreate('instructor', 'web');
        $unrelatedInstructor->assignRole('instructor');

        $this->actingAs($target)->post("/events/{$event->id}/register");
        $reg = EventRegistration::where('event_id', $event->id)->where('user_id', $target->id)->first();

        $this->actingAs($unrelatedInstructor)->post("/events/{$event->id}/cancel-registration", ['user_id' => $target->id])->assertRedirect();

        $this->assertDatabaseHas('event_registrations', ['id' => $reg->id, 'status' => 'cancelled']);
    }

    public function test_an_unrelated_member_cannot_unregister_someone_else(): void
    {
        $event = $this->event();
        $target = $this->member();
        $stranger = $this->member();

        $this->actingAs($target)->post("/events/{$event->id}/register");

        $this->actingAs($stranger)->post("/events/{$event->id}/cancel-registration", ['user_id' => $target->id])->assertForbidden();

        $this->assertDatabaseHas('event_registrations', ['event_id' => $event->id, 'user_id' => $target->id, 'status' => 'confirmed']);
    }

    public function test_the_unregister_button_is_offered_to_the_family_member_and_the_original_registrar_but_not_a_stranger(): void
    {
        $event = $this->event();
        $registrar = $this->member();
        $familyMember = $this->member();
        $stranger = $this->member();
        $target = $this->member();
        $target->detail->update(['family_id' => 2]);
        $familyMember->detail->update(['family_id' => 2]);

        $this->actingAs($registrar)->post("/events/{$event->id}/register", ['user_id' => $target->id]);

        $this->actingAs($familyMember)->get("/events/{$event->id}")->assertOk()->assertSee(route('events.cancel-registration', $event), false);
        $this->actingAs($registrar)->get("/events/{$event->id}")->assertOk()->assertSee(route('events.cancel-registration', $event), false);
        $this->actingAs($stranger)->get("/events/{$event->id}")->assertOk()->assertDontSee('name="user_id" value="'.$target->id.'"', false);
    }

    public function test_email_participants_is_offered_to_any_instructor_not_just_this_events_own(): void
    {
        $event = $this->event();
        $participant = $this->member();
        $this->actingAs($participant)->post("/events/{$event->id}/register");

        $unrelatedInstructor = $this->member();
        SpatieRole::findOrCreate('instructor', 'web');
        $unrelatedInstructor->assignRole('instructor');
        $plainMember = $this->member();

        $this->actingAs($unrelatedInstructor)->get("/events/{$event->id}")->assertOk()->assertSee(__('Email participants'));
        $this->actingAs($plainMember)->get("/events/{$event->id}")->assertOk()->assertDontSee(__('Email participants'));
    }

    public function test_show_hide_cancelled_is_offered_to_everyone_and_reveals_the_cancelled_list(): void
    {
        $event = $this->event();
        $cancelledMember = $this->member();
        $this->actingAs($cancelledMember)->post("/events/{$event->id}/register");
        $this->actingAs($cancelledMember)->post("/events/{$event->id}/cancel-registration");

        $plainMember = $this->member();

        $response = $this->actingAs($plainMember)->get("/events/{$event->id}");

        $response->assertOk();
        $response->assertSee(__('show/hide cancelled'));
        $response->assertSee($cancelledMember->detail->first_name);
    }
}
