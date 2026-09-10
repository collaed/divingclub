<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * "Directeur technique" — a delegated admin role. Starts with just
 * `manage federations` (the /admin/federations certification-list area); more
 * gear-related permissions can be ticked on the Roles & Permissions screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        $perm = Permission::firstOrCreate(['name' => 'manage federations', 'guard_name' => 'web']);

        $technicalDir = Role::firstOrCreate(['name' => 'technical_dir', 'guard_name' => 'web']);
        $technicalDir->givePermissionTo($perm);

        // bureau_master keeps everything.
        Role::where('name', 'bureau_master')->first()?->givePermissionTo($perm);

        // Legacy roles table (users.role_id / RolePermissionController listings).
        if (DB::getSchemaBuilder()->hasTable('roles') && ! DB::table('roles')->where('slug', 'technical_dir')->exists()) {
            DB::table('roles')->insert([
                'name' => 'Directeur technique',
                'slug' => 'technical_dir',
                'description' => 'Delegated admin — certification lists (and, if granted, gear)',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'technical_dir')->first()?->delete();
        Permission::where('name', 'manage federations')->first()?->delete();
        if (DB::getSchemaBuilder()->hasTable('roles')) {
            DB::table('roles')->where('slug', 'technical_dir')->delete();
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
