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

            // === LIFRAS/Belgian-specific (2020 rules: "Qui plonge avec qui?") ===
            // P1★ supervised
            ['name' => 'LIFRAS P1★ supervised ≤15m — P3★+ leader', 'scope' => 'LIFRAS', 'diver_condition' => 'P1', 'dive_mode' => 'supervised', 'min_leader_rank' => 60, 'leader_category' => 'diver', 'max_depth' => 15, 'max_group_size' => 4, 'description' => 'P1★ can dive to 15m max with a P3★ or P4★ as dive leader.'],
            ['name' => 'LIFRAS P1★ supervised ≤20m — AM+ leader', 'scope' => 'LIFRAS', 'diver_condition' => 'P1', 'dive_mode' => 'supervised', 'min_leader_rank' => 90, 'leader_category' => 'instructor', 'max_depth' => 20, 'max_group_size' => 4, 'description' => 'P1★ can dive to 20m max with an Assistant Moniteur or higher as leader.'],

            // P2★ supervised
            ['name' => 'LIFRAS P2★ supervised ≤30m — P3★+ leader', 'scope' => 'LIFRAS', 'diver_condition' => 'P2', 'dive_mode' => 'supervised', 'min_leader_rank' => 60, 'leader_category' => 'diver', 'max_depth' => 30, 'max_group_size' => 4, 'description' => 'P2★ can dive to 30m max with a P3★ or P4★ as dive leader.'],
            ['name' => 'LIFRAS P2★ supervised ≤40m — AM+ leader', 'scope' => 'LIFRAS', 'diver_condition' => 'P2', 'dive_mode' => 'supervised', 'min_leader_rank' => 90, 'leader_category' => 'instructor', 'max_depth' => 40, 'max_group_size' => 4, 'description' => 'P2★ can dive to 40m max with an Assistant Moniteur or higher as leader.'],

            // P2★ autonomous (2020 rule)
            ['name' => 'LIFRAS P2★+P2★ autonomous ≤20m', 'scope' => 'LIFRAS', 'diver_condition' => 'P2+P2', 'dive_mode' => 'autonomous', 'min_leader_rank' => 40, 'leader_category' => 'diver', 'max_depth' => 20, 'max_group_size' => 3, 'description' => 'Two P2★ divers (or higher) can dive autonomously to 20m. Rule since 01/01/2020.'],

            // P3★ autonomous
            ['name' => 'LIFRAS P3★+P2★ autonomous ≤30m', 'scope' => 'LIFRAS', 'diver_condition' => 'P2+P3', 'dive_mode' => 'autonomous', 'min_leader_rank' => 60, 'leader_category' => 'diver', 'max_depth' => 30, 'max_group_size' => 3, 'description' => 'P3★ leading a P2★ can dive autonomously to 30m.'],
            ['name' => 'LIFRAS P3★+P3★ autonomous ≤40m', 'scope' => 'LIFRAS', 'diver_condition' => 'P3+P3', 'dive_mode' => 'autonomous', 'min_leader_rank' => 60, 'leader_category' => 'diver', 'max_depth' => 40, 'max_group_size' => 3, 'description' => 'Two P3★ divers (or higher) can dive autonomously to 40m.'],

            // P4★ as chef de palanquée
            ['name' => 'LIFRAS P4★ leading P1★ ≤15m', 'scope' => 'LIFRAS', 'diver_condition' => 'P1', 'dive_mode' => 'supervised', 'min_leader_rank' => 70, 'leader_category' => 'diver', 'max_depth' => 15, 'max_group_size' => 4, 'description' => 'P4★ (Chef de Palanquée) can lead P1★ divers to 15m.'],
            ['name' => 'LIFRAS P4★ leading P2★ ≤40m', 'scope' => 'LIFRAS', 'diver_condition' => 'P2', 'dive_mode' => 'supervised', 'min_leader_rank' => 70, 'leader_category' => 'diver', 'max_depth' => 40, 'max_group_size' => 4, 'description' => 'P4★ (Chef de Palanquée) can lead P2★ divers to 40m.'],

            // Training dives (LIFRAS-specific)
            ['name' => 'LIFRAS P1★ formation (1-2) — MC+ required', 'scope' => 'LIFRAS', 'diver_condition' => 'P1_training', 'dive_mode' => 'training', 'min_leader_rank' => 100, 'leader_category' => 'instructor', 'max_depth' => 20, 'max_group_size' => 4, 'description' => 'First 2 formation dives for P1★ require Moniteur Club (M1★) minimum.'],
            ['name' => 'LIFRAS P1★ formation (3-5) — AM+ required', 'scope' => 'LIFRAS', 'diver_condition' => 'P1_training', 'dive_mode' => 'training', 'min_leader_rank' => 90, 'leader_category' => 'instructor', 'max_depth' => 20, 'max_group_size' => 4, 'description' => 'Formation dives 3-5 for P1★ require Assistant Moniteur (AM) minimum.'],

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
