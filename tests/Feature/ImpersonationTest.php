<?php

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

#[Group('p1')]
class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::upsert([['id' => 2, 'name' => 'Member', 'slug' => 'member'], ['id' => 6, 'name' => 'Bureau Master', 'slug' => 'bureau_master']], ['id']);
        foreach (['member', 'bureau_master'] as $r) {
            SpatieRole::findOrCreate($r, 'web');
        }
        MemberStatus::upsert([['id' => 1, 'name' => 'Active', 'slug' => 'active']], ['id']);
    }

    private function admin(): User
    {
        $u = User::create(['primary_email' => fake()->unique()->safeEmail(), 'password' => 'P', 'role_id' => 6, 'status_id' => 1, 'email_verified_at' => now()]);
        $u->assignRole('bureau_master');
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'Admin', 'last_name' => 'User']);

        return $u;
    }

    private function member(): User
    {
        $u = User::create(['primary_email' => fake()->unique()->safeEmail(), 'password' => 'P', 'role_id' => 2, 'status_id' => 1, 'email_verified_at' => now()]);
        $u->assignRole('member');
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'Member', 'last_name' => 'User']);

        return $u;
    }

    public function test_admin_can_impersonate_member(): void
    {
        $admin = $this->admin();
        $member = $this->member();

        $this->actingAs($admin)->post("/admin/members/{$member->id}/impersonate")->assertRedirect();
        $this->assertEquals($member->id, auth()->id());
    }

    public function test_impersonation_stores_original_user(): void
    {
        $admin = $this->admin();
        $member = $this->member();

        $this->actingAs($admin)->post("/admin/members/{$member->id}/impersonate");
        $this->assertEquals($admin->id, session('original_user_id'));
    }

    public function test_stop_impersonation_restores_admin(): void
    {
        $admin = $this->admin();
        $member = $this->member();

        // Start impersonation
        $response = $this->actingAs($admin)->post("/admin/members/{$member->id}/impersonate");
        $response->assertRedirect();

        // Stop impersonation — use the session from the impersonate call
        $response = $this->get('/admin/stop-impersonation');
        $response->assertRedirect();

        $this->assertEquals($admin->id, auth()->id());
    }

    public function test_member_cannot_impersonate(): void
    {
        $member = $this->member();
        $other = $this->member();

        $this->actingAs($member)->post("/admin/members/{$other->id}/impersonate")->assertStatus(403);
    }
}
