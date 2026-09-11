<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Club Partnerships and the Dive Group Planner are still being built out and
 * shouldn't be visible to the whole bureau yet. Each gets its own delegable
 * permission (same pattern as `view analytics`, `manage federations`,
 * `manage equipment`) so it can be opened up to the right group later
 * without touching code — for now, bureau_master only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $bureauMaster = Role::where('name', 'bureau_master')->first();

        $partnerships = Permission::firstOrCreate(['name' => 'manage partnerships', 'guard_name' => 'web']);
        $bureauMaster?->givePermissionTo($partnerships);

        $diveGroups = Permission::firstOrCreate(['name' => 'manage dive groups', 'guard_name' => 'web']);
        $bureauMaster?->givePermissionTo($diveGroups);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'manage partnerships')->first()?->delete();
        Permission::where('name', 'manage dive groups')->first()?->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
