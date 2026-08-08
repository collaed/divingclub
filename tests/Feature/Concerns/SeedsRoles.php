<?php

namespace Tests\Feature\Concerns;

use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Seeds minimal roles and permissions needed for admin controller tests.
 */
trait SeedsRoles
{
    protected function seedRoles(): void
    {
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'Member', 'slug' => 'member']);
        DB::table($roleTable)->insertOrIgnore(['id' => 6, 'name' => 'Bureau Master', 'slug' => 'bureau_master']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'active']);

        $bureauRole = SpatieRole::findOrCreate('bureau_master', 'web');
        SpatieRole::findOrCreate('bureau_finance', 'web');
        SpatieRole::findOrCreate('bureau_technical', 'web');
        SpatieRole::findOrCreate('instructor', 'web');
        SpatieRole::findOrCreate('member', 'web');

        // Give bureau_master all permissions used in admin tests
        $permissions = ['manage seasons', 'manage events', 'manage members', 'manage settings', 'send email', 'view finances'];
        foreach ($permissions as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $bureauRole->syncPermissions($permissions);
    }

    protected function createBureauUser(): User
    {
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        $roleId = DB::table($roleTable)->where('slug', 'bureau_master')->value('id') ?? 6;

        $user = User::create([
            'username' => 'bureau'.uniqid(),
            'primary_email' => 'bureau'.uniqid().'@test.com',
            'password' => 'Password1',
            'role_id' => $roleId,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('bureau_master');
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'Admin', 'last_name' => 'Test']);

        return $user;
    }

    protected function createMemberUser(): User
    {
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        $roleId = DB::table($roleTable)->where('slug', 'member')->value('id') ?? 2;

        $user = User::create([
            'username' => 'member'.uniqid(),
            'primary_email' => 'member'.uniqid().'@test.com',
            'password' => 'Password1',
            'role_id' => $roleId,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('member');
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'Member', 'last_name' => 'Test']);

        return $user;
    }
}
