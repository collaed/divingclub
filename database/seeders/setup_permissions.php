<?php

/**
 * Set up granular role-permission matrix and assign default permissions.
 *
 * Run: php artisan tinker --execute 'require "database/seeders/setup_permissions.php";'
 */

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

echo "🔐 Setting up granular permissions...\n\n";

// Ensure all permissions exist
$perms = [
    'manage members',
    'manage events',
    'manage equipment',
    'manage articles',
    'manage payments',
    'manage settings',
    'send newsletters',
    'manage backups',
    'view audit logs',
    'manage dive sites',
    'manage votes',
    'impersonate users',
    'manage roles',        // new
    'manage partnerships', // new
    'send email',          // new
    'verify documents',    // new
    'manage seasons',      // new
    'view email stats',    // new
];

foreach ($perms as $p) {
    Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
}
echo '  Permissions: '.Permission::count()."\n";

// Define the matrix
$matrix = [
    'bureau_master' => $perms, // all permissions

    'bureau_technical' => [
        'manage members', 'manage events', 'manage equipment', 'manage articles',
        'manage dive sites', 'manage votes', 'send email', 'send newsletters',
        'verify documents', 'manage seasons', 'view email stats', 'view audit logs',
        'manage partnerships',
    ],

    'bureau_finance' => [
        'manage members', 'manage payments', 'send email', 'view email stats',
        'view audit logs', 'verify documents',
    ],

    'instructor' => [
        'manage events', 'verify documents', 'manage dive sites',
    ],

    'member' => [],
    'public' => [],
];

foreach ($matrix as $roleName => $permissions) {
    $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    $role->syncPermissions($permissions);
    echo "  {$roleName}: ".count($permissions)." permissions\n";
}

echo "\n✅ Done. Matrix:\n";
echo str_pad('Permission', 25).str_pad('master', 8).str_pad('tech', 8).str_pad('finance', 8).str_pad('instr', 8)."\n";
echo str_repeat('-', 57)."\n";
foreach ($perms as $p) {
    echo str_pad($p, 25);
    foreach (['bureau_master', 'bureau_technical', 'bureau_finance', 'instructor'] as $r) {
        $has = in_array($p, $matrix[$r]) ? '  ✓' : '  ·';
        echo str_pad($has, 8);
    }
    echo "\n";
}
