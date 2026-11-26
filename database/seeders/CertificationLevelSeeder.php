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
            'SDI' => 'Scuba Diving International',
            'TDI' => 'Technical Diving International',
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
                // Junior/youth diver ranks
                ['code' => 'Bronze', 'name' => 'Jeune Plongeur Bronze', 'category' => 'diver', 'rank' => 4, 'equivalence_group' => null],
                ['code' => 'Argent', 'name' => 'Jeune Plongeur Argent', 'category' => 'diver', 'rank' => 6, 'equivalence_group' => null],
                ['code' => 'Or', 'name' => 'Jeune Plongeur Or', 'category' => 'diver', 'rank' => 8, 'equivalence_group' => null],
                // Freediving (apnée) track — "ATP" is the sigle FFESSM itself
                // prints next to this brevet on the club export.
                ['code' => 'ATP', 'name' => 'Pass Apnéiste', 'category' => 'diver', 'rank' => 4, 'equivalence_group' => null],
                ['code' => 'APNEA_1', 'name' => 'Apnéiste / Indoor Freediver 1 CMAS', 'category' => 'diver', 'rank' => 15, 'equivalence_group' => null],
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
                ['code' => 'E1', 'name' => 'Initiateur', 'category' => 'instructor', 'rank' => 100, 'equivalence_group' => 'instr_1'],
                ['code' => 'E2', 'name' => 'Initiateur + Guide de Palanquée (N4+Init)', 'category' => 'instructor', 'rank' => 110, 'equivalence_group' => 'instr_2'],
                ['code' => 'E3', 'name' => 'Moniteur Fédéral 1er degré (MF1)', 'category' => 'instructor', 'rank' => 120, 'equivalence_group' => 'instr_3'],
                ['code' => 'E4', 'name' => 'Moniteur Fédéral 2ème degré (MF2)', 'category' => 'instructor', 'rank' => 130, 'equivalence_group' => 'instr_4'],
                ['code' => 'E5', 'name' => 'Moniteur National (MN)', 'category' => 'instructor', 'rank' => 140, 'equivalence_group' => null],
                // Specialties
                ['code' => 'NITROX', 'name' => 'Qualification Nitrox', 'category' => 'specialty', 'rank' => 200, 'equivalence_group' => 'nitrox'],
                ['code' => 'NITROX_C', 'name' => 'Qualification Nitrox Confirmé', 'category' => 'specialty', 'rank' => 210, 'equivalence_group' => 'nitrox_adv'],
                ['code' => 'TRIMIX', 'name' => 'Qualification Trimix', 'category' => 'specialty', 'rank' => 220, 'equivalence_group' => 'trimix'],
                ['code' => 'RIFAP', 'name' => 'Réactions et Intervention Face à un Accident de Plongée', 'category' => 'specialty', 'rank' => 230, 'equivalence_group' => 'rescue'],
                ['code' => 'RIFA_APNEE', 'name' => 'RIFA Apnée', 'category' => 'specialty', 'rank' => 235, 'equivalence_group' => null],
                ['code' => 'DRYSUIT', 'name' => 'Combinaison étanche', 'category' => 'specialty', 'rank' => 240, 'equivalence_group' => null],
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

            // ================================================================
            // SDI — Scuba Diving International (recreational sister agency of TDI)
            // ================================================================
            'SDI' => [
                // Short / intro courses
                ['code' => 'SNORKELER', 'name' => 'Snorkeler', 'category' => 'specialty', 'rank' => 1, 'equivalence_group' => null],
                ['code' => 'SCUBA_DISCOVERY', 'name' => 'Scuba Discovery', 'category' => 'specialty', 'rank' => 2, 'equivalence_group' => null],
                ['code' => 'FUTURE_BUDDIES', 'name' => 'Future Buddies', 'category' => 'specialty', 'rank' => 3, 'equivalence_group' => null],
                ['code' => 'SHALLOW_WATER', 'name' => 'Shallow Water Diver', 'category' => 'specialty', 'rank' => 4, 'equivalence_group' => null],
                ['code' => 'INDOOR_DIVER', 'name' => 'Indoor Diver', 'category' => 'specialty', 'rank' => 5, 'equivalence_group' => null],
                ['code' => 'INACTIVE_REFRESHER', 'name' => 'Inactive Diver / Refresher', 'category' => 'specialty', 'rank' => 6, 'equivalence_group' => null],
                ['code' => 'SCUBILITY', 'name' => 'Scubility', 'category' => 'specialty', 'rank' => 7, 'equivalence_group' => null],
                ['code' => 'SCUBILITY_INSTR', 'name' => 'SDI Scubility Instructor', 'category' => 'instructor', 'rank' => 96, 'equivalence_group' => null],

                // Core diver progression
                ['code' => 'OWD', 'name' => 'Open Water Scuba Diver', 'category' => 'diver', 'rank' => 20, 'equivalence_group' => 'cmas_1s'],
                ['code' => 'ADV_DIVER', 'name' => 'Advanced Diver', 'category' => 'diver', 'rank' => 35, 'equivalence_group' => 'cmas_2s'],
                ['code' => 'RESCUE', 'name' => 'Rescue Diver', 'category' => 'diver', 'rank' => 36, 'equivalence_group' => 'rescue'],
                ['code' => 'MASTER_DIVER', 'name' => 'Master Diver', 'category' => 'diver', 'rank' => 45, 'equivalence_group' => 'cmas_3s'],
                ['code' => 'DM', 'name' => 'SDI Divemaster', 'category' => 'diver', 'rank' => 46, 'equivalence_group' => 'cmas_3s'],

                // Instructor cadre
                ['code' => 'AI', 'name' => 'SDI Assistant Instructor', 'category' => 'instructor', 'rank' => 95, 'equivalence_group' => null],
                ['code' => 'OWSI', 'name' => 'SDI Instructor', 'category' => 'instructor', 'rank' => 100, 'equivalence_group' => 'instr_1'],
                ['code' => 'ELITE_INSTR', 'name' => 'SDI Elite Scuba Instructor', 'category' => 'instructor', 'rank' => 105, 'equivalence_group' => null],
                ['code' => 'NON_DIVING_INSTR', 'name' => 'SDI Non-Diving Specialty Instructor', 'category' => 'instructor', 'rank' => 106, 'equivalence_group' => null],
                ['code' => 'COURSE_DIR', 'name' => 'SDI Course Director', 'category' => 'instructor', 'rank' => 115, 'equivalence_group' => 'instr_3'],
                ['code' => 'IT', 'name' => 'SDI Instructor Trainer', 'category' => 'instructor', 'rank' => 130, 'equivalence_group' => 'instr_4'],

                // Specialties
                ['code' => 'ADV_ADVENTURE', 'name' => 'Advanced Adventure', 'category' => 'specialty', 'rank' => 200, 'equivalence_group' => null],
                ['code' => 'ADV_BUOYANCY', 'name' => 'Advanced Buoyancy Control', 'category' => 'specialty', 'rank' => 201, 'equivalence_group' => null],
                ['code' => 'AIR_FILL_TECH', 'name' => 'Air Fill Station Technician', 'category' => 'specialty', 'rank' => 202, 'equivalence_group' => null],
                ['code' => 'ALTITUDE', 'name' => 'Altitude Diver', 'category' => 'specialty', 'rank' => 203, 'equivalence_group' => null],
                ['code' => 'BOAT', 'name' => 'Boat Diver', 'category' => 'specialty', 'rank' => 204, 'equivalence_group' => null],
                ['code' => 'COMPUTER', 'name' => 'Computer Diver', 'category' => 'specialty', 'rank' => 205, 'equivalence_group' => null],
                ['code' => 'COMPUTER_NITROX', 'name' => 'Computer Nitrox Diver', 'category' => 'specialty', 'rank' => 206, 'equivalence_group' => 'nitrox'],
                ['code' => 'DEEP', 'name' => 'Deep Diver', 'category' => 'specialty', 'rank' => 207, 'equivalence_group' => null],
                ['code' => 'DPV', 'name' => 'Diver Propulsion Vehicle', 'category' => 'specialty', 'rank' => 208, 'equivalence_group' => null],
                ['code' => 'DRIFT', 'name' => 'Drift Diver', 'category' => 'specialty', 'rank' => 209, 'equivalence_group' => null],
                ['code' => 'DRYSUIT', 'name' => 'Dry Suit Diver', 'category' => 'specialty', 'rank' => 210, 'equivalence_group' => null],
                ['code' => 'EQUIP_SPECIALIST', 'name' => 'Equipment Specialist', 'category' => 'specialty', 'rank' => 211, 'equivalence_group' => null],
                ['code' => 'FULL_FACE_MASK', 'name' => 'Full Face Mask Diver', 'category' => 'specialty', 'rank' => 212, 'equivalence_group' => null],
                ['code' => 'ICE', 'name' => 'Ice Diver', 'category' => 'specialty', 'rank' => 213, 'equivalence_group' => null],
                ['code' => 'MARINE_ECO', 'name' => 'Marine Ecosystems Awareness', 'category' => 'specialty', 'rank' => 214, 'equivalence_group' => null],
                ['code' => 'NIGHT_LIMITED_VIS', 'name' => 'Night/Limited Visibility Diver', 'category' => 'specialty', 'rank' => 215, 'equivalence_group' => null],
                ['code' => 'RESEARCH', 'name' => 'Research Diver', 'category' => 'specialty', 'rank' => 216, 'equivalence_group' => null],
                ['code' => 'SEARCH_RECOVERY', 'name' => 'Search & Recovery Diver', 'category' => 'specialty', 'rank' => 217, 'equivalence_group' => null],
                ['code' => 'SHORE_BEACH', 'name' => 'Shore/Beach Diver', 'category' => 'specialty', 'rank' => 218, 'equivalence_group' => null],
                ['code' => 'SIDEMOUNT', 'name' => 'Sidemount Diver', 'category' => 'specialty', 'rank' => 219, 'equivalence_group' => null],
                ['code' => 'SOLO', 'name' => 'Solo Diver', 'category' => 'specialty', 'rank' => 220, 'equivalence_group' => null],
                ['code' => 'UW_HUNTER', 'name' => 'U/W Hunter & Collector', 'category' => 'specialty', 'rank' => 221, 'equivalence_group' => null],
                ['code' => 'UW_NAV', 'name' => 'U/W Navigation', 'category' => 'specialty', 'rank' => 222, 'equivalence_group' => null],
                ['code' => 'UW_PHOTO', 'name' => 'U/W Photographer', 'category' => 'specialty', 'rank' => 223, 'equivalence_group' => null],
                ['code' => 'UW_VIDEO', 'name' => 'U/W Videographer', 'category' => 'specialty', 'rank' => 224, 'equivalence_group' => null],
                ['code' => 'VISUAL_INSPECTION', 'name' => 'Visual Inspection Procedures', 'category' => 'specialty', 'rank' => 225, 'equivalence_group' => null],
                ['code' => 'WRECK', 'name' => 'Wreck Diver', 'category' => 'specialty', 'rank' => 226, 'equivalence_group' => null],
            ],

            // ================================================================
            // TDI — Technical Diving International (technical sister agency of SDI)
            // ================================================================
            'TDI' => [
                // Open circuit
                ['code' => 'NITROX', 'name' => 'Nitrox Diver', 'category' => 'diver', 'rank' => 10, 'equivalence_group' => 'nitrox'],
                ['code' => 'INTRO_TECH', 'name' => 'Intro to Tech', 'category' => 'diver', 'rank' => 15, 'equivalence_group' => null],
                ['code' => 'ADV_NITROX', 'name' => 'Advanced Nitrox Diver', 'category' => 'diver', 'rank' => 20, 'equivalence_group' => 'nitrox_adv'],
                ['code' => 'DECO_PROC', 'name' => 'Decompression Procedures Diver', 'category' => 'diver', 'rank' => 30, 'equivalence_group' => null],
                ['code' => 'HELITROX', 'name' => 'Helitrox Diver', 'category' => 'diver', 'rank' => 32, 'equivalence_group' => null],
                ['code' => 'EXT_RANGE', 'name' => 'Extended Range Diver', 'category' => 'diver', 'rank' => 40, 'equivalence_group' => null],
                ['code' => 'TRIMIX', 'name' => 'Trimix Diver', 'category' => 'diver', 'rank' => 42, 'equivalence_group' => null],
                ['code' => 'ADV_TRIMIX', 'name' => 'Advanced Trimix Diver', 'category' => 'diver', 'rank' => 50, 'equivalence_group' => 'trimix'],

                // Rebreather
                ['code' => 'CCR_AIR_DIL', 'name' => 'Air Diluent CCR', 'category' => 'diver', 'rank' => 60, 'equivalence_group' => null],
                ['code' => 'CCR_SCR', 'name' => 'Semi Closed Rebreather', 'category' => 'diver', 'rank' => 61, 'equivalence_group' => null],
                ['code' => 'CCR_AIR_DIL_DECO', 'name' => 'Air Diluent Deco CCR', 'category' => 'diver', 'rank' => 65, 'equivalence_group' => null],
                ['code' => 'CCR_HELITROX', 'name' => 'Helitrox CCR', 'category' => 'diver', 'rank' => 66, 'equivalence_group' => null],
                ['code' => 'CCR_MIXED_GAS', 'name' => 'Mixed Gas CCR', 'category' => 'diver', 'rank' => 70, 'equivalence_group' => null],
                ['code' => 'CCR_ADV_MIXED_GAS', 'name' => 'Advanced Mixed Gas CCR', 'category' => 'diver', 'rank' => 75, 'equivalence_group' => null],

                // Overhead
                ['code' => 'CAVERN_DISCOVERY', 'name' => 'Cavern Discovery', 'category' => 'diver', 'rank' => 79, 'equivalence_group' => null],
                ['code' => 'CAVERN', 'name' => 'Cavern Diver', 'category' => 'diver', 'rank' => 80, 'equivalence_group' => null],
                ['code' => 'INTRO_CAVE', 'name' => 'Intro to Cave Diver', 'category' => 'diver', 'rank' => 85, 'equivalence_group' => null],
                ['code' => 'FULL_CAVE', 'name' => 'Full Cave Diver', 'category' => 'diver', 'rank' => 90, 'equivalence_group' => null],
                ['code' => 'CCR_CAVERN', 'name' => 'Rebreather Cavern Diver', 'category' => 'diver', 'rank' => 93, 'equivalence_group' => null],
                ['code' => 'CCR_INTRO_CAVE', 'name' => 'Rebreather Intro to Cave Diver', 'category' => 'diver', 'rank' => 94, 'equivalence_group' => null],
                ['code' => 'CCR_FULL_CAVE', 'name' => 'Rebreather Full Cave Diver', 'category' => 'diver', 'rank' => 95, 'equivalence_group' => null],

                // Overhead / service / misc specialties
                ['code' => 'ADV_WRECK', 'name' => 'Advanced Wreck Diver', 'category' => 'specialty', 'rank' => 200, 'equivalence_group' => null],
                ['code' => 'DPV', 'name' => 'Diver Propulsion Vehicle', 'category' => 'specialty', 'rank' => 201, 'equivalence_group' => null],
                ['code' => 'MINE', 'name' => 'Mine Diver', 'category' => 'specialty', 'rank' => 202, 'equivalence_group' => null],
                ['code' => 'CAVE_SURVEY', 'name' => 'Cave Survey', 'category' => 'specialty', 'rank' => 203, 'equivalence_group' => null],
                ['code' => 'STAGE_CAVE', 'name' => 'Stage Cave', 'category' => 'specialty', 'rank' => 204, 'equivalence_group' => null],
                ['code' => 'CAVE_DPV', 'name' => 'Cave DPV', 'category' => 'specialty', 'rank' => 205, 'equivalence_group' => null],
                ['code' => 'SIDEMOUNT', 'name' => 'TDI Sidemount', 'category' => 'specialty', 'rank' => 206, 'equivalence_group' => null],
                ['code' => 'NITROX_BLENDER', 'name' => 'Nitrox Blender', 'category' => 'specialty', 'rank' => 210, 'equivalence_group' => null],
                ['code' => 'ADV_BLENDER', 'name' => 'Advanced Blender', 'category' => 'specialty', 'rank' => 211, 'equivalence_group' => null],
                ['code' => 'O2_SERVICE', 'name' => 'O2 Service Technician', 'category' => 'specialty', 'rank' => 212, 'equivalence_group' => null],

                // Professional
                ['code' => 'NON_DIVING_INSTR', 'name' => 'TDI Non-Diving Specialty Instructor', 'category' => 'instructor', 'rank' => 97, 'equivalence_group' => null],
                ['code' => 'DM', 'name' => 'TDI Divemaster', 'category' => 'diver', 'rank' => 98, 'equivalence_group' => 'cmas_3s'],
                ['code' => 'INSTR', 'name' => 'TDI Instructor', 'category' => 'instructor', 'rank' => 100, 'equivalence_group' => 'instr_1'],
                ['code' => 'INSTR_TRAINER', 'name' => 'TDI Instructor Trainer', 'category' => 'instructor', 'rank' => 120, 'equivalence_group' => 'instr_4'],
            ],
        ];
    }
}
