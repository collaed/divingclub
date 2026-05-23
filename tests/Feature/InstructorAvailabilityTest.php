<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MemberDetail;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class InstructorAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'Member', 'slug' => 'member']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'active']);
        SpatieRole::findOrCreate('member', 'web');
        SpatieRole::findOrCreate('instructor', 'web');
        SpatieRole::findOrCreate('instructor_apnea', 'web');
        SpatieRole::findOrCreate('bureau_master', 'web');
        SpatieRole::findOrCreate('bureau_technical', 'web');
    }

    public function test_availability_page_loads_for_member(): void
    {
        $user = $this->createUser('member');
        Season::create(['year' => date('Y'), 'name' => date('Y'), 'start_date' => now()->startOfYear(), 'end_date' => now()->endOfYear(), 'is_active' => true]);
        $this->actingAs($user)->get('/availability')->assertOk();
    }

    public function test_guest_cannot_access_availability(): void
    {
        $this->get('/availability')->assertRedirect('/login');
    }

    public function test_instructor_can_toggle_availability(): void
    {
        $instructor = $this->createUser('instructor');
        $season = Season::create(['year' => date('Y'), 'name' => date('Y'), 'start_date' => now()->startOfYear(), 'end_date' => now()->endOfYear()]);
        $event = Event::create([
            'title' => 'Pool Training',
            'event_date' => now()->next('Wednesday'),
            'event_time' => '17:00',
            'event_type' => 'pool',
            'season_id' => $season->id,
            'status' => 'open',
        ]);

        $this->actingAs($instructor)->post('/availability/toggle', [
            'event_id' => $event->id,
        ])->assertOk();

        $this->assertDatabaseHas('instructor_availabilities', [
            'user_id' => $instructor->id,
            'event_id' => $event->id,
        ]);
    }

    public function test_member_cannot_toggle_availability(): void
    {
        $member = $this->createUser('member');
        $season = Season::create(['year' => date('Y'), 'name' => date('Y'), 'start_date' => now()->startOfYear(), 'end_date' => now()->endOfYear()]);
        $event = Event::create([
            'title' => 'Pool Training',
            'event_date' => now()->next('Wednesday'),
            'event_time' => '17:00',
            'event_type' => 'pool',
            'season_id' => $season->id,
            'status' => 'open',
        ]);

        $this->actingAs($member)->post('/availability/toggle', [
            'event_id' => $event->id,
        ])->assertForbidden();
    }

    private function createUser(string $role = 'member'): User
    {
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        $roleId = DB::table($roleTable)->where('slug', $role)->value('id')
            ?? DB::table($roleTable)->where('name', $role)->value('id')
            ?? DB::table($roleTable)->where('slug', 'member')->value('id')
            ?? 2;

        $u = User::create([
            'username' => fake()->userName(),
            'primary_email' => fake()->unique()->safeEmail(),
            'password' => 'Password1',
            'role_id' => $roleId,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $u->assignRole($role);
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'Test', 'last_name' => 'Instructor']);

        return $u;
    }
}
