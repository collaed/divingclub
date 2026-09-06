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
            ['name' => 'Actif',           'slug' => 'actif',           'description' => 'Active member (external)'],
            ['name' => 'Externe',         'slug' => 'externe',         'description' => 'External member'],
            ['name' => 'Fonctionnaire',   'slug' => 'fonctionnaire',   'description' => 'Civil servant member'],
            ['name' => 'Associé',         'slug' => 'associe',         'description' => 'Associate member'],
            ['name' => 'Assimilé',        'slug' => 'assimile',        'description' => 'Assimilated member'],
            ['name' => 'Honoraire',       'slug' => 'honoraire',       'description' => 'Honorary member (active, no fee)'],
            ['name' => 'Sympathisant',    'slug' => 'sympathisant',    'description' => 'Supporter (lighter membership)'],
            ['name' => 'Junior',          'slug' => 'junior',          'description' => 'Junior member (under 18)'],
            ['name' => 'Enfant',          'slug' => 'enfant',          'description' => 'Child member'],
            ['name' => 'Famille',         'slug' => 'famille',         'description' => 'Family member'],
            ['name' => 'Ancien membre',   'slug' => 'former',          'description' => 'Former / lapsed member (inactive)'],
        ];

        foreach ($statuses as $status) {
            DB::table('member_statuses')->updateOrInsert(['slug' => $status['slug']], array_merge($status, [
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }
    }
}
