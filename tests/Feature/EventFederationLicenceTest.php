<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Federation;
use App\Models\MemberDetail;
use App\Models\MemberLicence;
use App\Models\MemberStatus;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class EventFederationLicenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::upsert([['id' => 2, 'name' => 'Member', 'slug' => 'member'], ['id' => 6, 'name' => 'Bureau Master', 'slug' => 'bureau_master']], ['id']);
        SpatieRole::findOrCreate('member', 'web');
        SpatieRole::findOrCreate('bureau_master', 'web');
        // events/show.blade.php checks hasAnyRole(['instructor', 'instructor_apnea', 'assistant'])
        // on every render (unrelated to federation licences) — Spatie throws
        // RoleDoesNotExist if the role was never created at all, so these
        // need to exist even though this test file never assigns them.
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

    private function bureau(): User
    {
        $u = User::create(['primary_email' => fake()->unique()->safeEmail(), 'password' => 'P', 'role_id' => 6, 'status_id' => 1, 'email_verified_at' => now()]);
        $u->assignRole('bureau_master');

        return $u;
    }

    private function federation(string $acronym = 'FFESSM'): Federation
    {
        return Federation::create(['acronym' => $acronym, 'full_name' => $acronym]);
    }

    private function licence(User $user, Federation $federation, array $attrs = []): MemberLicence
    {
        return MemberLicence::create(array_merge([
            'user_id' => $user->id,
            'federation_id' => $federation->id,
            'licence_request_pending' => false,
        ], $attrs));
    }

    private function event(array $attrs = []): Event
    {
        $creator = User::first() ?? $this->member();

        return Event::create(array_merge([
            'title' => 'Test Trip', 'event_type' => 'social', 'event_date' => now()->addDays(7),
            'status' => 'scheduled', 'created_by' => $creator->id,
        ], $attrs));
    }

    public function test_has_active_licence_true_for_current_season_year(): void
    {
        $fed = $this->federation();
        $u = $this->member();
        $this->licence($u, $fed, ['season' => '2026']);

        $this->assertTrue($u->hasActiveLicence($fed, Carbon::parse('2026-06-15')));
    }

    public function test_has_active_licence_false_once_season_year_has_passed(): void
    {
        $fed = $this->federation();
        $u = $this->member();
        $this->licence($u, $fed, ['season' => '2025']);

        $this->assertFalse($u->hasActiveLicence($fed, Carbon::parse('2026-06-15')));
    }

    public function test_has_active_licence_false_while_request_is_pending(): void
    {
        $fed = $this->federation();
        $u = $this->member();
        $this->licence($u, $fed, ['season' => '2026', 'licence_request_pending' => true]);

        $this->assertFalse($u->hasActiveLicence($fed, Carbon::parse('2026-06-15')));
    }

    public function test_september_early_renewal_covers_through_the_following_year(): void
    {
        $fed = $this->federation();
        $u = $this->member();
        // Taken after the 1 Sept rollover -> recorded for next year's season,
        // but still covers the remainder of the current year too.
        $this->licence($u, $fed, ['season' => '2027']);

        $this->assertTrue($u->hasActiveLicence($fed, Carbon::parse('2026-09-15')));
        $this->assertTrue($u->hasActiveLicence($fed, Carbon::parse('2027-12-31')));
        $this->assertFalse($u->hasActiveLicence($fed, Carbon::parse('2028-01-01')));
    }

    public function test_registering_without_an_active_licence_is_held_as_pending(): void
    {
        $fed = $this->federation();
        $event = $this->event(['required_federation_id' => $fed->id, 'event_date' => Carbon::parse('2026-06-15')]);
        $u = $this->member();

        $this->actingAs($u)->post("/events/{$event->id}/register")->assertSessionHas('success');

        $this->assertDatabaseHas('event_registrations', ['event_id' => $event->id, 'user_id' => $u->id, 'status' => 'pending_licence']);
    }

    public function test_registering_with_an_active_licence_confirms_normally(): void
    {
        $fed = $this->federation();
        $event = $this->event(['required_federation_id' => $fed->id, 'event_date' => Carbon::parse('2026-06-15')]);
        $u = $this->member();
        $this->licence($u, $fed, ['season' => '2026']);

        $this->actingAs($u)->post("/events/{$event->id}/register")->assertSessionHas('success');

        $this->assertDatabaseHas('event_registrations', ['event_id' => $event->id, 'user_id' => $u->id, 'status' => 'confirmed']);
    }

    public function test_recheck_licence_fails_while_still_unlicensed(): void
    {
        $fed = $this->federation();
        $event = $this->event(['required_federation_id' => $fed->id, 'event_date' => Carbon::parse('2026-06-15')]);
        $u = $this->member();
        $this->actingAs($u)->post("/events/{$event->id}/register");
        $reg = EventRegistration::where('event_id', $event->id)->where('user_id', $u->id)->first();

        $this->actingAs($this->bureau())->post("/events/{$event->id}/recheck-licence", ['registration_id' => $reg->id])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('event_registrations', ['id' => $reg->id, 'status' => 'pending_licence']);
    }

    public function test_recheck_licence_confirms_once_active_licence_is_on_file(): void
    {
        $fed = $this->federation();
        $event = $this->event(['required_federation_id' => $fed->id, 'event_date' => Carbon::parse('2026-06-15')]);
        $u = $this->member();
        $this->actingAs($u)->post("/events/{$event->id}/register");
        $reg = EventRegistration::where('event_id', $event->id)->where('user_id', $u->id)->first();

        $this->licence($u, $fed, ['season' => '2026']);

        $this->actingAs($this->bureau())->post("/events/{$event->id}/recheck-licence", ['registration_id' => $reg->id])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('event_registrations', ['event_id' => $event->id, 'user_id' => $u->id, 'status' => 'confirmed']);
    }

    public function test_recheck_licence_falls_back_to_waiting_if_event_filled_up_meanwhile(): void
    {
        $fed = $this->federation();
        $event = $this->event(['required_federation_id' => $fed->id, 'event_date' => Carbon::parse('2026-06-15'), 'max_participants' => 1, 'waiting_list_enabled' => true]);
        $u = $this->member();
        $this->actingAs($u)->post("/events/{$event->id}/register");
        $reg = EventRegistration::where('event_id', $event->id)->where('user_id', $u->id)->first();

        // Someone else, already licensed, takes the only slot while $u is still pending licence.
        $other = $this->member();
        $this->licence($other, $fed, ['season' => '2026']);
        $this->actingAs($other)->post("/events/{$event->id}/register");

        $this->licence($u, $fed, ['season' => '2026']);
        $this->actingAs($this->bureau())->post("/events/{$event->id}/recheck-licence", ['registration_id' => $reg->id])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('event_registrations', ['event_id' => $event->id, 'user_id' => $u->id, 'status' => 'waiting']);
    }

    public function test_event_without_a_required_federation_behaves_as_before(): void
    {
        $event = $this->event();
        $u = $this->member();

        $this->actingAs($u)->post("/events/{$event->id}/register")->assertSessionHas('success');

        $this->assertDatabaseHas('event_registrations', ['event_id' => $event->id, 'user_id' => $u->id, 'status' => 'confirmed']);
    }

    public function test_an_already_confirmed_member_whose_licence_has_since_lapsed_is_flagged_on_the_event_page(): void
    {
        $fed = $this->federation();
        $event = $this->event(['required_federation_id' => $fed->id, 'event_date' => Carbon::parse('2026-06-15')]);
        $u = $this->member();
        $this->licence($u, $fed, ['season' => '2026']);
        $this->actingAs($u)->post("/events/{$event->id}/register")->assertSessionHas('success');

        // Licence has since expired relative to the event date (frozen "now" irrelevant — the check uses event_date).
        MemberLicence::where('user_id', $u->id)->update(['season' => '2025']);

        $this->actingAs($this->bureau())->get("/events/{$event->id}")
            ->assertOk()
            ->assertSee('No longer has an active');
    }

    public function test_a_still_compliant_confirmed_member_is_not_flagged(): void
    {
        $fed = $this->federation();
        $event = $this->event(['required_federation_id' => $fed->id, 'event_date' => Carbon::parse('2026-06-15')]);
        $u = $this->member();
        $this->licence($u, $fed, ['season' => '2026']);
        $this->actingAs($u)->post("/events/{$event->id}/register")->assertSessionHas('success');

        $this->actingAs($this->bureau())->get("/events/{$event->id}")
            ->assertOk()
            ->assertDontSee('No longer has an active');
    }

    public function test_an_assigned_instructor_without_an_active_licence_is_flagged(): void
    {
        $fed = $this->federation();
        $instructor = $this->member();
        $event = $this->event(['required_federation_id' => $fed->id, 'event_date' => Carbon::parse('2026-06-15'), 'instructor_id' => $instructor->id]);

        $this->actingAs($this->bureau())->get("/events/{$event->id}")
            ->assertOk()
            ->assertSee('No active')
            ->assertSee($instructor->name);
    }

    public function test_an_assigned_instructor_with_an_active_licence_is_not_flagged(): void
    {
        $fed = $this->federation();
        $instructor = $this->member();
        $this->licence($instructor, $fed, ['season' => '2026']);
        $event = $this->event(['required_federation_id' => $fed->id, 'event_date' => Carbon::parse('2026-06-15'), 'instructor_id' => $instructor->id]);

        $this->actingAs($this->bureau())->get("/events/{$event->id}")
            ->assertOk()
            ->assertDontSee('No active');
    }

    public function test_an_assistant_without_an_active_licence_is_flagged(): void
    {
        $fed = $this->federation();
        $assistant = $this->member();
        $event = $this->event(['required_federation_id' => $fed->id, 'event_date' => Carbon::parse('2026-06-15'), 'assistant_ids' => [$assistant->id]]);

        $this->actingAs($this->bureau())->get("/events/{$event->id}")
            ->assertOk()
            ->assertSee('No active')
            ->assertSee($assistant->name);
    }

    public function test_bureau_can_flag_a_member_as_handling_ffessm_externally_from_the_licence_row(): void
    {
        $fed = $this->federation('FFESSM');
        $u = $this->member();
        $lic = $this->licence($u, $fed, ['season' => '2026']);
        $bureau = $this->bureau();
        Permission::findOrCreate('manage members', 'web');
        $bureau->givePermissionTo('manage members');

        $this->actingAs($bureau)->post(route('profile.update.licence', $lic), [
            'season' => '2026',
            'licence_request_pending' => '0',
            'ffessm_handled_externally' => '1',
        ])->assertSessionHas('success');

        $this->assertTrue($u->fresh()->detail->fresh()->ffessm_handled_externally);
    }
}
