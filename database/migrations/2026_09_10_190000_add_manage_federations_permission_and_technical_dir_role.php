<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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

        Role::firstOrCreate(['name' => 'technical_dir', 'guard_name' => 'web'])->givePermissionTo($perm);

        // bureau_master keeps everything.
        Role::where('name', 'bureau_master')->first()?->givePermissionTo($perm);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'technical_dir')->first()?->delete();
        Permission::where('name', 'manage federations')->first()?->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
