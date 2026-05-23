<?php

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class AdminMemberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'Member', 'slug' => 'member']);
        DB::table($roleTable)->insertOrIgnore(['id' => 6, 'name' => 'Bureau Master', 'slug' => 'bureau_master']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'active']);
        SpatieRole::findOrCreate('member', 'web');
        $r = SpatieRole::findOrCreate('bureau_master', 'web');
        $r->givePermissionTo(Permission::findOrCreate('manage members', 'web'));
    }

    public function test_admin_members_index_loads(): void
    {
        $user = $this->createUser('bureau_master');
        $this->actingAs($user)->get('/admin/members')->assertOk();
    }

    public function test_admin_can_view_member_profile(): void
    {
        $admin = $this->createUser('bureau_master');
        $member = $this->createUser('member');
        $this->actingAs($admin)->get("/admin/members/{$member->id}/profile")->assertOk();
    }

    public function test_admin_can_update_member_info(): void
    {
        $admin = $this->createUser('bureau_master');
        $member = $this->createUser('member');

        $response = $this->actingAs($admin)->post("/admin/members/{$member->id}/info", [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'sex' => 'M',
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_member_cannot_access_admin_members(): void
    {
        $user = $this->createUser('member');
        $this->actingAs($user)->get('/admin/members')->assertForbidden();
    }

    private function createUser(string $role = 'member'): User
    {
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        $roleId = DB::table($roleTable)->where('slug', $role)->value('id')
            ?? DB::table($roleTable)->where('name', $role)->value('id') ?? 2;

        $u = User::create([
            'username' => fake()->userName(),
            'primary_email' => fake()->unique()->safeEmail(),
            'password' => 'Password1',
            'role_id' => $roleId,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $u->assignRole($role);
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'Test', 'last_name' => 'User']);

        return $u;
    }
}
