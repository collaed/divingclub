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
}
