<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Public', 'slug' => 'public', 'description' => 'Unauthenticated visitor'],
            ['name' => 'Member', 'slug' => 'member', 'description' => 'Registered club member'],
            ['name' => 'Instructor', 'slug' => 'instructor', 'description' => 'Diving instructor'],
            ['name' => 'Bureau Finance', 'slug' => 'bureau_finance', 'description' => 'Financial administrator'],
            ['name' => 'Bureau Technical', 'slug' => 'bureau_technical', 'description' => 'Equipment administrator'],
            ['name' => 'Bureau Master', 'slug' => 'bureau_master', 'description' => 'Full administrative access'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(['slug' => $role['slug']], array_merge($role, [
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }
    }
}
