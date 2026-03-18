<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FederationSeeder extends Seeder
{
    public function run(): void
    {
        $federations = [
            ['acronym' => 'FFESSM', 'full_name' => 'Fédération Française d\'Études et de Sports Sous-Marins'],
            ['acronym' => 'LIFRAS', 'full_name' => 'Ligue Francophone de Recherches et d\'Activités Subaquatiques'],
            ['acronym' => 'FLASSA', 'full_name' => 'Fédération Luxembourgeoise des Activités Subaquatiques et de Sauvetage'],
        ];

        foreach ($federations as $fed) {
            DB::table('federations')->updateOrInsert(['acronym' => $fed['acronym']], array_merge($fed, [
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }
    }
}
