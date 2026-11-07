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

class EventRoutineFoldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::upsert([['id' => 2, 'name' => 'Member', 'slug' => 'member']], ['id']);
        SpatieRole::findOrCreate('member', 'web');
        // events/show.blade.php checks hasAnyRole(['instructor', 'instructor_apnea', 'assistant'])
        // on every render; Spatie throws RoleDoesNotExist if the role was never created at all.
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

    private function event(array $attrs = []): Event
    {
        $creator = User::first() ?? $this->member();

        return Event::create(array_merge([
            'title' => 'Test', 'event_type' => 'social', 'event_date' => now()->addDays(7),
            'status' => 'scheduled', 'created_by' => $creator->id,
        ], $attrs));
    }

    public function test_first_visit_shows_venue_details_expanded(): void
    {
        $event = $this->event(['location' => 'Piscine Belair']);
        $member = $this->member();

        $response = $this->actingAs($member)->get("/events/{$event->id}");

        $response->assertOk();
        $response->assertSee('aria-expanded="true"', false);
    }

    public function test_revisiting_the_same_event_folds_the_venue_details(): void
    {
        $event = $this->event(['location' => 'Piscine Belair']);
        $member = $this->member();

        $this->actingAs($member)->get("/events/{$event->id}");
        $response = $this->actingAs($member)->get("/events/{$event->id}");

        $response->assertOk();
        $response->assertSee('aria-expanded="false"', false);
    }

    public function test_familiar_location_folds_the_venue_details_on_first_visit(): void
    {
        $pastEvent = $this->event(['location' => 'Piscine Belair', 'event_date' => now()->subDays(30)]);
        $member = $this->member();
        EventRegistration::create(['event_id' => $pastEvent->id, 'user_id' => $member->id, 'status' => 'confirmed']);

        $newEvent = $this->event(['location' => 'Piscine Belair']);
        $response = $this->actingAs($member)->get("/events/{$newEvent->id}");

        $response->assertOk();
        $response->assertSee('aria-expanded="false"', false);
    }
}
