<?php

namespace Database\Seeders;

use App\Models\CertificationLevel;
use App\Models\Federation;
use Illuminate\Database\Seeder;

class CertificationLevelSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure all federations exist
        $feds = [
            'FFESSM' => 'Fédération Française d\'Études et de Sports Sous-Marins',
            'LIFRAS' => 'Ligue Francophone de Recherches et d\'Activités Subaquatiques',
            'FLASSA' => 'Fédération Luxembourgeoise des Activités Subaquatiques et de Sauvetage Aquatique',
            'NELOS' => 'Nederlandstalige Liga voor Onderwateronderzoek en -Sport',
            'VDST' => 'Verband Deutscher Sporttaucher',
            'NASDS' => 'National Association of Scuba Diving Schools',
            'PADI' => 'Professional Association of Diving Instructors',
            'SSI' => 'Scuba Schools International',
            'UCPA' => 'Union nationale des Centres sportifs de Plein Air',
            'BSAC' => 'British Sub-Aqua Club',
            'CMAS' => 'Confédération Mondiale des Activités Subaquatiques',
        ];

        foreach ($feds as $acronym => $name) {
            Federation::firstOrCreate(['acronym' => $acronym], ['full_name' => $name]);
        }

        $data = $this->getCertData();

        foreach ($data as $fedAcronym => $levels) {
            $fed = Federation::where('acronym', $fedAcronym)->first();
            if (! $fed) {
                continue;
            }
            foreach ($levels as $level) {
                CertificationLevel::updateOrCreate(
                    ['federation_id' => $fed->id, 'code' => $level['code']],
                    $level
                );
            }
        }

        echo 'Seeded '.CertificationLevel::count().' certification levels across '.count($data)." federations.\n";
    }

    private function getCertData(): array
    {
        return [
            // ================================================================
            // FFESSM — French system (Niveaux)
            // ================================================================
            'FFESSM' => [
                // Diver levels
                ['code' => 'PE20', 'name' => 'Plongeur Encadré 20m', 'category' => 'diver', 'rank' => 10, 'equivalence_group' => 'cmas_1s'],
                ['code' => 'N1', 'name' => 'Niveau 1 — Plongeur Encadré 20m', 'category' => 'diver', 'rank' => 20, 'equivalence_group' => 'cmas_1s'],
                ['code' => 'PE40', 'name' => 'Plongeur Encadré 40m', 'category' => 'diver', 'rank' => 25, 'equivalence_group' => 'cmas_2s'],
                ['code' => 'PA20', 'name' => 'Plongeur Autonome 20m', 'category' => 'diver', 'rank' => 30, 'equivalence_group' => 'cmas_2s'],
                ['code' => 'N2', 'name' => 'Niveau 2 — Plongeur Autonome 20m / Encadré 40m', 'category' => 'diver', 'rank' => 40, 'equivalence_group' => 'cmas_2s'],
                ['code' => 'PA40', 'name' => 'Plongeur Autonome 40m', 'category' => 'diver', 'rank' => 50, 'equivalence_group' => 'cmas_3s'],
                ['code' => 'N3', 'name' => 'Niveau 3 — Plongeur Autonome 60m', 'category' => 'diver', 'rank' => 60, 'equivalence_group' => 'cmas_3s'],
                ['code' => 'N4', 'name' => 'Niveau 4 — Guide de Palanquée', 'category' => 'diver', 'rank' => 70, 'equivalence_group' => 'cmas_3s_gp'],
                ['code' => 'N5', 'name' => 'Niveau 5 — Directeur de Plongée', 'category' => 'diver', 'rank' => 80, 'equivalence_group' => null],
                // Instructor levels
                ['code' => 'E1', 'name' => 'Initiateur — Enseignant Niveau 1', 'category' => 'instructor', 'rank' => 100, 'equivalence_group' => 'instr_1'],
                ['code' => 'E2', 'name' => 'Moniteur Fédéral 1er degré', 'category' => 'instructor', 'rank' => 110, 'equivalence_group' => 'instr_2'],
                ['code' => 'E3', 'name' => 'Moniteur Fédéral 2ème degré', 'category' => 'instructor', 'rank' => 120, 'equivalence_group' => 'instr_3'],
                ['code' => 'E4', 'name' => 'Moniteur National 1er degré', 'category' => 'instructor', 'rank' => 130, 'equivalence_group' => 'instr_4'],
                ['code' => 'E5', 'name' => 'Moniteur National 2ème degré', 'category' => 'instructor', 'rank' => 140, 'equivalence_group' => null],
                // Specialties
                ['code' => 'NITROX', 'name' => 'Qualification Nitrox', 'category' => 'specialty', 'rank' => 200, 'equivalence_group' => 'nitrox'],
                ['code' => 'NITROX_C', 'name' => 'Qualification Nitrox Confirmé', 'category' => 'specialty', 'rank' => 210, 'equivalence_group' => 'nitrox_adv'],
                ['code' => 'TRIMIX', 'name' => 'Qualification Trimix', 'category' => 'specialty', 'rank' => 220, 'equivalence_group' => 'trimix'],
                ['code' => 'RIFAP', 'name' => 'Réactions et Intervention Face à un Accident de Plongée', 'category' => 'specialty', 'rank' => 230, 'equivalence_group' => 'rescue'],
            ],

            // ================================================================
            // LIFRAS — Belgian French-speaking system
            // ================================================================
            'LIFRAS' => [
                ['code' => 'P1', 'name' => 'Plongeur 1 étoile', 'category' => 'diver', 'rank' => 20, 'equivalence_group' => 'cmas_1s'],
                ['code' => 'P2', 'name' => 'Plongeur 2 étoiles', 'category' => 'diver', 'rank' => 40, 'equivalence_group' => 'cmas_2s'],
                ['code' => 'P3', 'name' => 'Plongeur 3 étoiles', 'category' => 'diver', 'rank' => 60, 'equivalence_group' => 'cmas_3s'],
                ['code' => 'P4', 'name' => 'Plongeur 4 étoiles — Chef de Palanquée', 'category' => 'diver', 'rank' => 70, 'equivalence_group' => 'cmas_3s_gp'],
                ['code' => 'M1', 'name' => 'Moniteur 1 étoile', 'category' => 'instructor', 'rank' => 100, 'equivalence_group' => 'instr_1'],
                ['code' => 'M2', 'name' => 'Moniteur 2 étoiles', 'category' => 'instructor', 'rank' => 110, 'equivalence_group' => 'instr_2'],
                ['code' => 'M3', 'name' => 'Moniteur 3 étoiles', 'category' => 'instructor', 'rank' => 120, 'equivalence_group' => 'instr_3'],
                ['code' => 'NITROX', 'name' => 'Plongeur Nitrox', 'category' => 'specialty', 'rank' => 200, 'equivalence_group' => 'nitrox'],
                ['code' => 'NITROX_ADV', 'name' => 'Plongeur Nitrox Avancé', 'category' => 'specialty', 'rank' => 210, 'equivalence_group' => 'nitrox_adv'],
                ['code' => 'SECOURISME', 'name' => 'Brevet de Secourisme', 'category' => 'specialty', 'rank' => 230, 'equivalence_group' => 'rescue'],
            ],

            // ================================================================
            // FLASSA — Luxembourg system
            // ================================================================
            'FLASSA' => [
                ['code' => 'P1', 'name' => 'Plongeur 1 étoile', 'category' => 'diver', 'rank' => 20, 'equivalence_group' => 'cmas_1s'],
                ['code' => 'P2', 'name' => 'Plongeur 2 étoiles', 'category' => 'diver', 'rank' => 40, 'equivalence_group' => 'cmas_2s'],
                ['code' => 'P3', 'name' => 'Plongeur 3 étoiles', 'category' => 'diver', 'rank' => 60, 'equivalence_group' => 'cmas_3s'],
                ['code' => 'P4', 'name' => 'Plongeur 4 étoiles', 'category' => 'diver', 'rank' => 70, 'equivalence_group' => 'cmas_3s_gp'],
                ['code' => 'M1', 'name' => 'Moniteur 1 étoile', 'category' => 'instructor', 'rank' => 100, 'equivalence_group' => 'instr_1'],
                ['code' => 'M2', 'name' => 'Moniteur 2 étoiles', 'category' => 'instructor', 'rank' => 110, 'equivalence_group' => 'instr_2'],
                ['code' => 'M3', 'name' => 'Moniteur 3 étoiles', 'category' => 'instructor', 'rank' => 120, 'equivalence_group' => 'instr_3'],
                ['code' => 'NITROX', 'name' => 'Plongeur Nitrox', 'category' => 'specialty', 'rank' => 200, 'equivalence_group' => 'nitrox'],
            ],

            // ================================================================
            // NELOS — Belgian Dutch-speaking system
            // ================================================================
            'NELOS' => [
                ['code' => 'D1', 'name' => 'Duiker 1 ster', 'category' => 'diver', 'rank' => 20, 'equivalence_group' => 'cmas_1s'],
                ['code' => 'D2', 'name' => 'Duiker 2 sterren', 'category' => 'diver', 'rank' => 40, 'equivalence_group' => 'cmas_2s'],
                ['code' => 'D3', 'name' => 'Duiker 3 sterren', 'category' => 'diver', 'rank' => 60, 'equivalence_group' => 'cmas_3s'],
                ['code' => 'D4', 'name' => 'Duiker 4 sterren — Palanquéeleider', 'category' => 'diver', 'rank' => 70, 'equivalence_group' => 'cmas_3s_gp'],
                ['code' => 'I1', 'name' => 'Instructeur 1 ster', 'category' => 'instructor', 'rank' => 100, 'equivalence_group' => 'instr_1'],
                ['code' => 'I2', 'name' => 'Instructeur 2 sterren', 'category' => 'instructor', 'rank' => 110, 'equivalence_group' => 'instr_2'],
                ['code' => 'I3', 'name' => 'Instructeur 3 sterren', 'category' => 'instructor', 'rank' => 120, 'equivalence_group' => 'instr_3'],
                ['code' => 'NITROX', 'name' => 'Nitrox Duiker', 'category' => 'specialty', 'rank' => 200, 'equivalence_group' => 'nitrox'],
            ],

            // ================================================================
            // VDST — German system
            // ================================================================
            'VDST' => [
                ['code' => 'DTSA_B', 'name' => 'DTSA Basic — Grundtauchschein', 'category' => 'diver', 'rank' => 10, 'equivalence_group' => null],
                ['code' => 'DTSA_1S', 'name' => 'DTSA * — Taucher 1 Stern', 'category' => 'diver', 'rank' => 20, 'equivalence_group' => 'cmas_1s'],
                ['code' => 'DTSA_2S', 'name' => 'DTSA ** — Taucher 2 Sterne', 'category' => 'diver', 'rank' => 40, 'equivalence_group' => 'cmas_2s'],
                ['code' => 'DTSA_3S', 'name' => 'DTSA *** — Taucher 3 Sterne', 'category' => 'diver', 'rank' => 60, 'equivalence_group' => 'cmas_3s'],
                ['code' => 'TL1', 'name' => 'Tauchlehrer 1 Stern', 'category' => 'instructor', 'rank' => 100, 'equivalence_group' => 'instr_1'],
                ['code' => 'TL2', 'name' => 'Tauchlehrer 2 Sterne', 'category' => 'instructor', 'rank' => 110, 'equivalence_group' => 'instr_2'],
                ['code' => 'TL3', 'name' => 'Tauchlehrer 3 Sterne', 'category' => 'instructor', 'rank' => 120, 'equivalence_group' => 'instr_3'],
                ['code' => 'NITROX', 'name' => 'Nitrox Taucher', 'category' => 'specialty', 'rank' => 200, 'equivalence_group' => 'nitrox'],
                ['code' => 'NITROX_ADV', 'name' => 'Nitrox Fortgeschritten', 'category' => 'specialty', 'rank' => 210, 'equivalence_group' => 'nitrox_adv'],
            ],

            // ================================================================
            // PADI — Professional Association of Diving Instructors
            // ================================================================
            'PADI' => [
                ['code' => 'SD', 'name' => 'Scuba Diver', 'category' => 'diver', 'rank' => 10, 'equivalence_group' => null],
                ['code' => 'OWD', 'name' => 'Open Water Diver', 'category' => 'diver', 'rank' => 20, 'equivalence_group' => 'cmas_1s'],
                ['code' => 'AOWD', 'name' => 'Advanced Open Water Diver', 'category' => 'diver', 'rank' => 40, 'equivalence_group' => 'cmas_2s'],
                ['code' => 'RD', 'name' => 'Rescue Diver', 'category' => 'diver', 'rank' => 50, 'equivalence_group' => 'rescue'],
                ['code' => 'DM', 'name' => 'Divemaster', 'category' => 'diver', 'rank' => 60, 'equivalence_group' => 'cmas_3s'],
                ['code' => 'MSD', 'name' => 'Master Scuba Diver', 'category' => 'diver', 'rank' => 65, 'equivalence_group' => 'cmas_3s'],
                ['code' => 'OWSI', 'name' => 'Open Water Scuba Instructor', 'category' => 'instructor', 'rank' => 100, 'equivalence_group' => 'instr_1'],
                ['code' => 'MSDT', 'name' => 'Master Scuba Diver Trainer', 'category' => 'instructor', 'rank' => 110, 'equivalence_group' => 'instr_2'],
                ['code' => 'IDC_SI', 'name' => 'IDC Staff Instructor', 'category' => 'instructor', 'rank' => 120, 'equivalence_group' => 'instr_3'],
                ['code' => 'CD', 'name' => 'Course Director', 'category' => 'instructor', 'rank' => 130, 'equivalence_group' => 'instr_4'],
                ['code' => 'EFR', 'name' => 'Emergency First Response', 'category' => 'specialty', 'rank' => 230, 'equivalence_group' => 'rescue'],
                ['code' => 'NITROX', 'name' => 'Enriched Air Diver', 'category' => 'specialty', 'rank' => 200, 'equivalence_group' => 'nitrox'],
                ['code' => 'DEEP', 'name' => 'Deep Diver', 'category' => 'specialty', 'rank' => 205, 'equivalence_group' => null],
                ['code' => 'WRECK', 'name' => 'Wreck Diver', 'category' => 'specialty', 'rank' => 206, 'equivalence_group' => null],
                ['code' => 'TECTRIMIX', 'name' => 'Tec Trimix Diver', 'category' => 'specialty', 'rank' => 220, 'equivalence_group' => 'trimix'],
            ],

            // ================================================================
            // SSI — Scuba Schools International
            // ================================================================
            'SSI' => [
                ['code' => 'SD', 'name' => 'Scuba Diver', 'category' => 'diver', 'rank' => 10, 'equivalence_group' => null],
                ['code' => 'OWD', 'name' => 'Open Water Diver', 'category' => 'diver', 'rank' => 20, 'equivalence_group' => 'cmas_1s'],
                ['code' => 'AOWD', 'name' => 'Advanced Adventurer', 'category' => 'diver', 'rank' => 40, 'equivalence_group' => 'cmas_2s'],
                ['code' => 'STRESS_RESCUE', 'name' => 'Stress & Rescue', 'category' => 'diver', 'rank' => 50, 'equivalence_group' => 'rescue'],
                ['code' => 'DG', 'name' => 'Dive Guide', 'category' => 'diver', 'rank' => 60, 'equivalence_group' => 'cmas_3s'],
                ['code' => 'DM', 'name' => 'Divemaster', 'category' => 'diver', 'rank' => 65, 'equivalence_group' => 'cmas_3s'],
                ['code' => 'OWI', 'name' => 'Open Water Instructor', 'category' => 'instructor', 'rank' => 100, 'equivalence_group' => 'instr_1'],
                ['code' => 'AOWI', 'name' => 'Advanced Open Water Instructor', 'category' => 'instructor', 'rank' => 110, 'equivalence_group' => 'instr_2'],
                ['code' => 'DCI', 'name' => 'Dive Control Instructor', 'category' => 'instructor', 'rank' => 115, 'equivalence_group' => 'instr_2'],
                ['code' => 'IT', 'name' => 'Instructor Trainer', 'category' => 'instructor', 'rank' => 130, 'equivalence_group' => 'instr_4'],
                ['code' => 'NITROX', 'name' => 'Enriched Air Nitrox', 'category' => 'specialty', 'rank' => 200, 'equivalence_group' => 'nitrox'],
                ['code' => 'DEEP', 'name' => 'Deep Diving', 'category' => 'specialty', 'rank' => 205, 'equivalence_group' => null],
                ['code' => 'REACT_RIGHT', 'name' => 'React Right', 'category' => 'specialty', 'rank' => 230, 'equivalence_group' => 'rescue'],
            ],

            // ================================================================
            // UCPA — French outdoor sports
            // ================================================================
            'UCPA' => [
                ['code' => 'N1', 'name' => 'Niveau 1 UCPA', 'category' => 'diver', 'rank' => 20, 'equivalence_group' => 'cmas_1s'],
                ['code' => 'N2', 'name' => 'Niveau 2 UCPA', 'category' => 'diver', 'rank' => 40, 'equivalence_group' => 'cmas_2s'],
                ['code' => 'N3', 'name' => 'Niveau 3 UCPA', 'category' => 'diver', 'rank' => 60, 'equivalence_group' => 'cmas_3s'],
            ],

            // ================================================================
            // BSAC — British Sub-Aqua Club
            // ================================================================
            'BSAC' => [
                ['code' => 'OO', 'name' => 'Ocean Diver', 'category' => 'diver', 'rank' => 20, 'equivalence_group' => 'cmas_1s'],
                ['code' => 'SD', 'name' => 'Sports Diver', 'category' => 'diver', 'rank' => 40, 'equivalence_group' => 'cmas_2s'],
                ['code' => 'DL', 'name' => 'Dive Leader', 'category' => 'diver', 'rank' => 60, 'equivalence_group' => 'cmas_3s'],
                ['code' => 'AD', 'name' => 'Advanced Diver', 'category' => 'diver', 'rank' => 70, 'equivalence_group' => 'cmas_3s_gp'],
                ['code' => 'FC', 'name' => 'First Class Diver', 'category' => 'diver', 'rank' => 80, 'equivalence_group' => null],
                ['code' => 'OWI', 'name' => 'Open Water Instructor', 'category' => 'instructor', 'rank' => 100, 'equivalence_group' => 'instr_1'],
                ['code' => 'AI', 'name' => 'Advanced Instructor', 'category' => 'instructor', 'rank' => 110, 'equivalence_group' => 'instr_2'],
                ['code' => 'NI', 'name' => 'National Instructor', 'category' => 'instructor', 'rank' => 120, 'equivalence_group' => 'instr_3'],
                ['code' => 'NITROX', 'name' => 'Nitrox Diver', 'category' => 'specialty', 'rank' => 200, 'equivalence_group' => 'nitrox'],
            ],

            // ================================================================
            // NASDS — National Association of Scuba Diving Schools
            // ================================================================
            'NASDS' => [
                ['code' => 'OWD', 'name' => 'Open Water Diver', 'category' => 'diver', 'rank' => 20, 'equivalence_group' => 'cmas_1s'],
                ['code' => 'AWD', 'name' => 'Advanced Watermanship Diver', 'category' => 'diver', 'rank' => 40, 'equivalence_group' => 'cmas_2s'],
                ['code' => 'MSD', 'name' => 'Master Scuba Diver', 'category' => 'diver', 'rank' => 60, 'equivalence_group' => 'cmas_3s'],
                ['code' => 'INST', 'name' => 'Instructor', 'category' => 'instructor', 'rank' => 100, 'equivalence_group' => 'instr_1'],
            ],

            // ================================================================
            // CMAS — World Confederation (international reference)
            // ================================================================
            'CMAS' => [
                ['code' => '1S', 'name' => 'CMAS 1 Star Diver', 'category' => 'diver', 'rank' => 20, 'equivalence_group' => 'cmas_1s'],
                ['code' => '2S', 'name' => 'CMAS 2 Star Diver', 'category' => 'diver', 'rank' => 40, 'equivalence_group' => 'cmas_2s'],
                ['code' => '3S', 'name' => 'CMAS 3 Star Diver', 'category' => 'diver', 'rank' => 60, 'equivalence_group' => 'cmas_3s'],
                ['code' => '4S', 'name' => 'CMAS 4 Star Diver', 'category' => 'diver', 'rank' => 70, 'equivalence_group' => 'cmas_3s_gp'],
                ['code' => 'M1', 'name' => 'CMAS 1 Star Instructor', 'category' => 'instructor', 'rank' => 100, 'equivalence_group' => 'instr_1'],
                ['code' => 'M2', 'name' => 'CMAS 2 Star Instructor', 'category' => 'instructor', 'rank' => 110, 'equivalence_group' => 'instr_2'],
                ['code' => 'M3', 'name' => 'CMAS 3 Star Instructor', 'category' => 'instructor', 'rank' => 120, 'equivalence_group' => 'instr_3'],
                ['code' => 'M4', 'name' => 'CMAS 4 Star Instructor', 'category' => 'instructor', 'rank' => 130, 'equivalence_group' => 'instr_4'],
            ],
        ];
    }
}
