<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\PageVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class ActivityTrackingTest extends TestCase
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

        Route::middleware('web')->get('/__track_ping', fn () => response('pong'));
        Route::middleware('web')->post('/__track_post', fn () => response('posted'));
    }

    private function user(string $role = 'member'): User
    {
        $u = User::factory()->create(['status_id' => 1]);
        $u->assignRole($role);
        MemberDetail::factory()->create(['user_id' => $u->id, 'first_name' => 'Jane', 'last_name' => 'Doe']);

        return $u;
    }

    public function test_authenticated_request_sets_last_seen_at(): void
    {
        $u = $this->user();
        $this->assertNull($u->last_seen_at);

        $this->actingAs($u)->get('/__track_ping')->assertOk();

        $fresh = $u->fresh();
        $this->assertNotNull($fresh->last_seen_at);
        $this->assertTrue($fresh->last_seen_at->greaterThan(now()->subMinute()));
    }

    public function test_last_seen_at_is_throttled(): void
    {
        $u = $this->user();
        $stale = now()->subMinutes(2);
        User::withoutTimestamps(fn () => $u->forceFill(['last_seen_at' => $stale])->saveQuietly());

        $this->actingAs($u)->get('/__track_ping')->assertOk();

        $this->assertSame($stale->timestamp, $u->fresh()->last_seen_at->timestamp);
    }

    public function test_guest_request_records_nothing(): void
    {
        $this->get('/__track_ping')->assertOk();

        $this->assertDatabaseCount('page_visits', 0);
    }

    public function test_page_visit_is_logged_for_authenticated_get_when_enabled(): void
    {
        config(['tracking.page_visits' => true]);
        $u = $this->user();

        $this->actingAs($u)->get('/__track_ping')->assertOk();

        $this->assertDatabaseHas('page_visits', [
            'user_id' => $u->id,
            'method' => 'GET',
            'path' => '/__track_ping',
            'status' => 200,
        ]);
    }

    public function test_page_visit_is_not_logged_when_disabled(): void
    {
        config(['tracking.page_visits' => false]);
        $u = $this->user();

        $this->actingAs($u)->get('/__track_ping')->assertOk();

        $this->assertDatabaseCount('page_visits', 0);
        $this->assertNotNull($u->fresh()->last_seen_at);
    }

    public function test_non_get_requests_are_not_logged(): void
    {
        config(['tracking.page_visits' => true]);
        $u = $this->user();

        $this->actingAs($u)->post('/__track_post')->assertOk();

        $this->assertDatabaseCount('page_visits', 0);
    }

    public function test_prune_command_deletes_only_rows_past_retention(): void
    {
        config(['tracking.retention_days' => 3]);
        $u = $this->user();
        $old = PageVisit::factory()->create(['user_id' => $u->id, 'created_at' => now()->subDays(5)]);
        $recent = PageVisit::factory()->create(['user_id' => $u->id, 'created_at' => now()->subDay()]);

        $this->artisan('tracking:prune')->assertSuccessful();

        $this->assertDatabaseMissing('page_visits', ['id' => $old->id]);
        $this->assertDatabaseHas('page_visits', ['id' => $recent->id]);
    }

    public function test_bureau_master_sees_members_and_activity_tabs(): void
    {
        $master = $this->user('bureau_master');
        User::withoutTimestamps(fn () => $master->forceFill(['last_seen_at' => now()->subMinutes(3)])->saveQuietly());
        PageVisit::factory()->create([
            'user_id' => $master->id,
            'path' => '/events/42',
            'status' => 500,
            'created_at' => now()->subHour(),
        ]);

        $this->actingAs($master)->get('/admin/logins?tab=members')->assertOk()
            ->assertSee('Jane Doe')
            ->assertSee('data-sort-col', false)
            ->assertSee('data-sort-value="'.$master->last_seen_at->timestamp, false); // chronological sort key, not the humanised text
        $this->actingAs($master)->get('/admin/logins?tab=activity')->assertOk()->assertSee('View trail');
        $this->actingAs($master)->get('/admin/logins?tab=activity&user='.$master->id)->assertOk()->assertSee('/events/42');
    }

    public function test_non_bureau_master_cannot_open_the_page(): void
    {
        $this->actingAs($this->user('member'))->get('/admin/logins')->assertForbidden();
    }
}
