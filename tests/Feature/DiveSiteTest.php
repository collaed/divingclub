<?php

namespace Tests\Feature;

use App\Models\DiveSite;
use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class DiveSiteTest extends TestCase
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
        $r->givePermissionTo(Permission::findOrCreate('manage dive sites', 'web'));
    }

    public function test_dive_sites_index_loads(): void
    {
        $user = $this->createUser('bureau_master');
        $this->actingAs($user)->get('/admin/dive-sites')->assertOk();
    }

    public function test_bureau_can_create_dive_site(): void
    {
        $user = $this->createUser('bureau_master');
        $this->actingAs($user)->post('/admin/dive-sites', [
            'name' => 'Gravière du Fort',
            'country' => 'FR',
            'max_depth' => 40,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('dive_sites', ['name' => 'Gravière du Fort']);
    }

    public function test_bureau_can_update_dive_site(): void
    {
        $user = $this->createUser('bureau_master');
        $site = DiveSite::create(['name' => 'Old Name', 'country' => 'LU', 'is_active' => true]);

        $this->actingAs($user)->put("/admin/dive-sites/{$site->id}", [
            'name' => 'New Name',
            'country' => 'LU',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('dive_sites', ['id' => $site->id, 'name' => 'New Name']);
    }

    public function test_member_cannot_manage_dive_sites(): void
    {
        $user = $this->createUser('member');
        $this->actingAs($user)->get('/admin/dive-sites')->assertForbidden();
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
