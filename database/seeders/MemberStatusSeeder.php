<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MemberStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'Membre de droit', 'slug' => 'membre_de_droit', 'fee_multiplier' => 1.00, 'description' => 'Full rights member'],
            ['name' => 'Actif',           'slug' => 'actif',           'fee_multiplier' => 1.00, 'description' => 'Active member'],
            ['name' => 'Fonctionnaire',   'slug' => 'fonctionnaire',   'fee_multiplier' => 0.33, 'description' => 'Civil servant member'],
            ['name' => 'Honoraire',       'slug' => 'honoraire',       'fee_multiplier' => 0.00, 'description' => 'Honorary member'],
            ['name' => 'Junior',          'slug' => 'junior',          'fee_multiplier' => 0.50, 'description' => 'Junior member (under 18)'],
            ['name' => 'Famille',         'slug' => 'famille',         'fee_multiplier' => 0.80, 'description' => 'Family member'],
        ];

        foreach ($statuses as $status) {
            DB::table('member_statuses')->updateOrInsert(['slug' => $status['slug']], array_merge($status, [
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }
    }
}
