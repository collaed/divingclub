<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * - `view analytics` permission (Umami page) → bureau_master.
 * - `manage equipment` (existing) → technical_dir, so the delegated role also
 *   covers gear.
 */
return new class extends Migration
{
    public function up(): void
    {
        $analytics = Permission::firstOrCreate(['name' => 'view analytics', 'guard_name' => 'web']);
        Role::where('name', 'bureau_master')->first()?->givePermissionTo($analytics);

        $equipment = Permission::firstOrCreate(['name' => 'manage equipment', 'guard_name' => 'web']);
        Role::where('name', 'technical_dir')->first()?->givePermissionTo($equipment);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'technical_dir')->first()?->revokePermissionTo('manage equipment');
        Permission::where('name', 'view analytics')->first()?->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
