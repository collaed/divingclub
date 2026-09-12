<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class DiveGroupPlannerAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::upsert([['id' => 2, 'name' => 'Member', 'slug' => 'member']], ['id']);
        MemberStatus::upsert([['id' => 1, 'name' => 'Active', 'slug' => 'active']], ['id']);
        foreach (['member', 'instructor', 'instructor_apnea', 'bureau_master', 'assistant'] as $r) {
            SpatieRole::findOrCreate($r, 'web');
        }
    }

    private function user(string $role): User
    {
        $u = User::factory()->create(['status_id' => 1, 'email_verified_at' => now()]);
        $u->assignRole($role);
        MemberDetail::factory()->create(['user_id' => $u->id]);

        return $u;
    }

    private function event(): Event
    {
        return Event::create([
            'title' => 'Gravière', 'event_type' => 'dive', 'event_date' => now()->addWeek(),
            'status' => 'scheduled', 'created_by' => User::first()?->id ?? $this->user('bureau_master')->id,
        ]);
    }

    public function test_a_plain_member_cannot_reach_the_planner_or_its_validation(): void
    {
        $member = $this->user('member');
        $event = $this->event();

        $this->actingAs($member)->get("/events/{$event->id}/dive-groups")->assertForbidden();
        $this->actingAs($member)->get("/events/{$event->id}/dive-groups/validate")->assertForbidden();
    }

    public function test_instructors_and_bureau_can_reach_the_planner(): void
    {
        $event = $this->event();

        foreach (['instructor', 'instructor_apnea', 'bureau_master'] as $role) {
            $this->actingAs($this->user($role))
                ->get("/events/{$event->id}/dive-groups")->assertOk();
            $this->actingAs($this->user($role))
                ->get("/events/{$event->id}/dive-groups/validate")->assertOk();
        }
    }
}
