<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\LoginRecord;
use App\Models\MemberDetail;
use App\Models\PageVisit;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class LoginHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'Member', 'slug' => 'member']);
        DB::table($roleTable)->insertOrIgnore(['id' => 6, 'name' => 'Bureau Master', 'slug' => 'bureau_master']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'active']);
        foreach (['member', 'bureau_master', 'bureau_finance'] as $r) {
            SpatieRole::findOrCreate($r, 'web');
        }
    }

    private function user(string $role): User
    {
        $u = User::factory()->create(['status_id' => 1]);
        $u->assignRole($role);
        MemberDetail::factory()->create(['user_id' => $u->id, 'first_name' => 'Jane', 'last_name' => 'Doe']);

        return $u;
    }

    public function test_the_login_event_writes_a_record(): void
    {
        $u = $this->user('member');
        event(new Login('web', $u, false));

        $this->assertDatabaseHas('login_records', ['user_id' => $u->id, 'guard' => 'web']);
        $this->assertNotNull(LoginRecord::first()->created_at);
    }

    public function test_impersonation_does_not_create_a_login_record(): void
    {
        $u = $this->user('member');
        session(['impersonating' => 999]);

        event(new Login('web', $u, false));

        $this->assertDatabaseCount('login_records', 0);
    }

    public function test_bureau_master_sees_the_page_with_records(): void
    {
        $master = $this->user('bureau_master');
        LoginRecord::create(['user_id' => $master->id, 'ip_address' => '203.0.113.9', 'guard' => 'web',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/126.0']);

        $res = $this->actingAs($master)->get('/admin/logins');

        $res->assertOk();
        $res->assertSee('Login history');
        $res->assertSee('203.0.113.9');
        $res->assertSee('Chrome · Windows');
    }

    public function test_activity_trail_shows_the_visited_events_title(): void
    {
        $master = $this->user('bureau_master');
        $member = $this->user('member');
        $event = Event::factory()->create(['title' => 'Sortie Grotte Bleue']);

        PageVisit::create(['user_id' => $member->id, 'method' => 'GET', 'path' => "/events/{$event->id}", 'route_name' => 'events.show', 'status' => 200]);
        PageVisit::create(['user_id' => $member->id, 'method' => 'GET', 'path' => '/', 'route_name' => 'home', 'status' => 200]);

        $res = $this->actingAs($master)->get('/admin/logins?tab=activity&user='.$member->id);

        $res->assertOk()
            ->assertSee('Sortie Grotte Bleue')
            ->assertSee('Home');
    }

    public function test_non_bureau_master_is_forbidden(): void
    {
        $this->actingAs($this->user('bureau_finance'))->get('/admin/logins')->assertForbidden();
        $this->actingAs($this->user('member'))->get('/admin/logins')->assertForbidden();
    }
}
