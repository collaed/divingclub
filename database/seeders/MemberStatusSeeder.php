<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MemberStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'Membre de droit', 'slug' => 'membre_de_droit', 'description' => 'Full rights member'],
            ['name' => 'Actif',           'slug' => 'actif',           'description' => 'Active member'],
            ['name' => 'Fonctionnaire',   'slug' => 'fonctionnaire',   'description' => 'Civil servant member'],
            ['name' => 'Honoraire',       'slug' => 'honoraire',       'description' => 'Honorary member'],
            ['name' => 'Junior',          'slug' => 'junior',          'description' => 'Junior member (under 18)'],
            ['name' => 'Famille',         'slug' => 'famille',         'description' => 'Family member'],
        ];

        foreach ($statuses as $status) {
            DB::table('member_statuses')->updateOrInsert(['slug' => $status['slug']], array_merge($status, [
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }
    }
}
