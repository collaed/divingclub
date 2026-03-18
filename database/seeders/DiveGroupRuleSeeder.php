<?php

namespace Database\Seeders;

use App\Models\DiveGroupRule;
use Illuminate\Database\Seeder;

class DiveGroupRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            // === GLOBAL / CMAS rules ===
            // Uncertified divers (baptêmes, try-dives) must be with an instructor
            ['name' => 'Uncertified diver — instructor required', 'scope' => 'global', 'diver_condition' => 'no_cert', 'dive_mode' => 'supervised', 'min_leader_rank' => 100, 'leader_category' => 'instructor', 'max_depth' => 6, 'max_group_size' => 2, 'description' => 'Try-dive / baptême: instructor only, max 6m, 1 student per instructor'],

            // CMAS 1-star / PE20 / P1 — supervised to 20m, need guide de palanquée (rank ≥ 70) or instructor
            ['name' => 'CMAS 1★ supervised ≤20m — guide or instructor', 'scope' => 'global', 'diver_condition' => 'max_rank:25', 'dive_mode' => 'supervised', 'min_leader_rank' => 70, 'leader_category' => 'diver', 'max_depth' => 20, 'max_group_size' => 4, 'description' => 'PE20/P1/1★ divers need a Guide de Palanquée (N4/P4/4★) or instructor as leader, max 20m'],

            // CMAS 2-star / N2 / P2 — supervised to 40m, need guide de palanquée or instructor
            ['name' => 'CMAS 2★ supervised ≤40m — guide or instructor', 'scope' => 'global', 'diver_condition' => 'max_rank:45', 'dive_mode' => 'supervised', 'min_leader_rank' => 70, 'leader_category' => 'diver', 'max_depth' => 40, 'max_group_size' => 4, 'description' => 'PE40/N2/P2/2★ divers need a Guide de Palanquée or instructor, max 40m'],

            // CMAS 2-star autonomous to 20m — can buddy with same level
            ['name' => 'CMAS 2★ autonomous ≤20m — buddy pair', 'scope' => 'global', 'diver_condition' => 'min_rank:30', 'dive_mode' => 'autonomous', 'min_leader_rank' => 30, 'leader_category' => 'diver', 'max_depth' => 20, 'max_group_size' => 3, 'description' => 'PA20+ divers can dive autonomously to 20m in buddy pairs/trios'],

            // CMAS 3-star / N3 / P3 — autonomous to 60m
            ['name' => 'CMAS 3★ autonomous ≤60m — buddy pair', 'scope' => 'global', 'diver_condition' => 'min_rank:60', 'dive_mode' => 'autonomous', 'min_leader_rank' => 60, 'leader_category' => 'diver', 'max_depth' => 60, 'max_group_size' => 3, 'description' => 'N3/P3/3★ divers can dive autonomously to 60m'],

            // === Training mode: instructor always required ===
            ['name' => 'Training dive — instructor required', 'scope' => 'global', 'diver_condition' => 'any', 'dive_mode' => 'training', 'min_leader_rank' => 100, 'leader_category' => 'instructor', 'max_depth' => null, 'max_group_size' => 4, 'description' => 'Training dives require an instructor as group leader'],

            // === Certification / exam dive: higher-level instructor ===
            ['name' => 'Certification dive — E2/M2+ instructor required', 'scope' => 'global', 'diver_condition' => 'any', 'dive_mode' => 'certification', 'min_leader_rank' => 110, 'leader_category' => 'instructor', 'max_depth' => null, 'max_group_size' => 4, 'description' => 'Skill validation / certification dives require at least a 2★ instructor (E2/M2/MSDT)'],

            // === FFESSM-specific ===
            ['name' => 'FFESSM PE40 supervised ≤40m — E1+ initiateur', 'scope' => 'FFESSM', 'diver_condition' => 'max_rank:25', 'dive_mode' => 'supervised', 'min_leader_rank' => 100, 'leader_category' => 'instructor', 'max_depth' => 40, 'max_group_size' => 4, 'description' => 'FFESSM: PE20 divers in 20-40m zone need at least an Initiateur (E1)'],

            // === LIFRAS/Belgian-specific ===
            ['name' => 'LIFRAS P1 supervised ≤20m — P4/M1+ required', 'scope' => 'LIFRAS', 'diver_condition' => 'max_rank:25', 'dive_mode' => 'supervised', 'min_leader_rank' => 70, 'leader_category' => 'diver', 'max_depth' => 20, 'max_group_size' => 4, 'description' => 'LIFRAS: P1 divers need a Chef de Palanquée (P4) or Moniteur'],

            ['name' => 'LIFRAS P2 supervised ≤40m — P4/M1+ required', 'scope' => 'LIFRAS', 'diver_condition' => 'max_rank:45', 'dive_mode' => 'supervised', 'min_leader_rank' => 70, 'leader_category' => 'diver', 'max_depth' => 40, 'max_group_size' => 4, 'description' => 'LIFRAS: P2 divers need a Chef de Palanquée (P4) or Moniteur for 20-40m'],

            // === PADI-specific ===
            ['name' => 'PADI OWD supervised ≤18m — DM or instructor', 'scope' => 'PADI', 'diver_condition' => 'max_rank:25', 'dive_mode' => 'supervised', 'min_leader_rank' => 60, 'leader_category' => 'diver', 'max_depth' => 18, 'max_group_size' => 4, 'description' => 'PADI OWD divers need a Divemaster or Instructor, max 18m'],

            ['name' => 'PADI AOWD supervised ≤30m — DM or instructor', 'scope' => 'PADI', 'diver_condition' => 'max_rank:45', 'dive_mode' => 'supervised', 'min_leader_rank' => 60, 'leader_category' => 'diver', 'max_depth' => 30, 'max_group_size' => 4, 'description' => 'PADI AOWD divers need a Divemaster or Instructor, max 30m'],

            ['name' => 'PADI Rescue+ autonomous ≤40m — buddy pair', 'scope' => 'PADI', 'diver_condition' => 'min_rank:50', 'dive_mode' => 'autonomous', 'min_leader_rank' => 50, 'leader_category' => 'diver', 'max_depth' => 40, 'max_group_size' => 3, 'description' => 'PADI Rescue Diver+ can dive autonomously to 40m in buddy pairs'],
        ];

        foreach ($rules as $rule) {
            DiveGroupRule::updateOrCreate(
                ['name' => $rule['name']],
                $rule
            );
        }
    }
}
