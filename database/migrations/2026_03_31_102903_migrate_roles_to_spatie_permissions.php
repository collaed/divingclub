<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure Spatie roles exist (idempotent)
        $roleNames = ['public', 'member', 'instructor', 'bureau_finance', 'bureau_technical', 'bureau_master'];
        foreach ($roleNames as $name) {
            Role::findOrCreate($name, 'web');
        }

        // Migrate existing users from legacy role_id to Spatie pivot (skip if no legacy table)
        if (DB::getSchemaBuilder()->hasTable('legacy_roles')) {
            $legacyRoles = DB::table('legacy_roles')->get()->keyBy('id');
            $users = DB::table('users')->whereNotNull('role_id')->get();

            foreach ($users as $user) {
                $legacy = $legacyRoles->get($user->role_id);
                if ($legacy) {
                    $spatieRole = Role::findByName($legacy->slug, 'web');
                    DB::table('model_has_roles')->insertOrIgnore([
                        'role_id' => $spatieRole->id,
                        'model_type' => 'App\\Models\\User',
                        'model_id' => $user->id,
                    ]);
                }
            }
        }

        // Create permissions
        $permissions = [
            'manage members', 'manage events', 'manage equipment',
            'manage articles', 'manage payments', 'manage settings',
            'send newsletters', 'manage backups', 'view audit logs',
            'manage dive sites', 'manage votes', 'impersonate users',
        ];
        foreach ($permissions as $p) {
            Permission::findOrCreate($p, 'web');
        }

        // Assign permissions to roles
        Role::findByName('bureau_master', 'web')->givePermissionTo($permissions);

        foreach (['bureau_finance', 'bureau_technical'] as $slug) {
            Role::findByName($slug, 'web')->givePermissionTo([
                'manage members', 'manage events', 'manage equipment',
                'manage articles', 'manage payments', 'send newsletters',
                'manage dive sites', 'manage votes', 'view audit logs',
            ]);
        }

        Role::findByName('instructor', 'web')->givePermissionTo(['manage events', 'manage dive sites']);
    }

    public function down(): void
    {
        DB::table('model_has_roles')->truncate();
        DB::table('model_has_permissions')->truncate();
        DB::table('role_has_permissions')->truncate();
    }
};
