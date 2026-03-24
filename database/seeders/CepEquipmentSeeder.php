<?php

namespace Database\Seeders;

use App\Models\Equipment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CepEquipmentSeeder extends Seeder
{
    public function run(): void
    {
        DB::unprepared('DELETE FROM equipment');

        foreach ($this->items() as $item) {
            Equipment::create($item);
        }

        $this->command->info('CEP equipment seeded: '.count($this->items()).' items.');
    }

    private function items(): array
    {
        return array_merge(
            $this->blocAir(),
            $this->blocNitrox(),
            $this->oxygen(),
            $this->tampons(),
            $this->regulators(),
            $this->bcds(),
            $this->keys(),
        );
    }

    // ── Bloc Air (31 active) ──────────────────────────────────────────

    private function blocAir(): array
    {
        return [
            $this->tank('1', '86AA20334CA', 'SPIROTECHNIQUE', 'ROTH', '1986-03-01', 14.5, '12.0 L', 300, 200, '2021-10-15', '2026-10-14', null),
            $this->tank('7', '93AA44046CA', 'SPIROTECHNIQUE', 'ROTH', '1993-03-01', 14.3, '12.1 L', 300, 200, '2021-09-29', '2026-09-28', null),
            $this->tank('8', '93AA43950CA', 'SPIROTECHNIQUE', 'ROTH', '1993-03-01', 14.2, '12.1 L', 300, 200, '2021-09-29', '2026-09-28', '1 robinet cassé, utilisable sur l\'autre sortie'),
            $this->tank('9', '93AA43358CA', 'SPIROTECHNIQUE', 'ROTH', '1993-03-01', 14.0, '12.1 L', 300, 200, '2021-09-29', '2026-09-28', null),
            $this->tank('10', '93AA43989OA', 'SPIROTECHNIQUE', 'ROTH', '1993-03-01', 14.8, '12.1 L', 300, 200, '2021-09-29', '2026-09-28', 'Odeur d\'huile signalée 19/5/21'),
            $this->tank('11', '93AA43377CA', 'SPIROTECHNIQUE', 'ROTH', '1993-03-01', 14.9, '12.1 L', 300, 200, '2021-10-15', '2026-10-14', null),
            $this->tank('12', '91AA31241CA', 'SPIROTECHNIQUE', 'ROTH', '1991-01-01', 14.5, '12.1 L', 300, 200, '2022-02-17', '2027-02-16', 'Réépreuve par Alain en février 2022'),
            $this->tank('13', '93AA43983CA', 'SPIROTECHNIQUE', 'ROTH', '1993-03-01', 14.2, '12.1 L', 300, 200, '2021-10-15', '2026-10-14', null),
            $this->tank('14', '93AA48544CA', 'SPIROTECHNIQUE', 'ROTH', '1993-03-01', 14.6, '12.1 L', 300, 200, '2021-10-15', '2026-10-14', null),
            $this->tank('15', '91AA31208CA', 'SPIROTECHNIQUE', 'ROTH', '1991-01-01', 14.6, '12.1 L', 300, 200, '2021-10-15', '2026-10-14', null),
            $this->tank('16', '91AA42426CA', 'SPIROTECHNIQUE', 'ROTH', '1991-02-01', 14.6, '12.1 L', 300, 200, '2021-10-15', '2026-10-14', null),
            $this->tank('17', '91AA31103CA', 'SPIROTECHNIQUE', 'ROTH', '1991-01-01', 14.6, '12.1 L', 300, 200, '2021-10-15', '2026-10-14', null),
            $this->tank('18', '91AA31126CA', 'SPIROTECHNIQUE', 'ROTH', '1991-01-01', 14.5, '12.1 L', 300, 200, '2021-10-15', '2026-10-14', null),
            $this->tank('19', '93AA43488CA', 'SPIROTECHNIQUE', 'ROTH', '1993-03-01', 14.3, '12.1 L', 300, 200, '2021-10-15', '2026-10-14', null),
            $this->tank('20', '86AA524CA', 'SPIROTECHNIQUE', 'ROTH', '1985-01-01', 10.1, '9 L', 264, 176, '2021-09-29', '2026-09-28', 'Bi 2x9 séparé en 2 blocs individuel petit gabarit', 'Piscine Merl'),
            $this->tank('21', '85AA448CA', 'SPIROTECHNIQUE', 'ROTH', '1985-01-01', 10.1, '9 L', 264, 176, '2021-10-15', '2026-10-14', 'Bi 2x9 séparé en 2 blocs individuel petit gabarit', 'Piscine Merl'),
            $this->tank('22', '86AA481CA', 'SPIROTECHNIQUE', 'ROTH', '1985-01-01', 10.1, '9 L', 264, 176, '2021-09-29', '2026-09-28', 'Bi 2x9 séparé. Problème de robinetterie', 'Piscine Merl'),
            $this->tank('23', '86AA488CA', 'SPIROTECHNIQUE', 'ROTH', '1985-01-01', 10.1, '9 L', 264, 176, '2021-10-15', '2026-10-14', 'Bi 2x9 séparé en 2 blocs individuel petit gabarit', 'Piscine Merl'),
            $this->tank('24', '10302863', 'Breathing Apparatus', 'ECS', '2008-01-01', 11.9, '10 L', 345, 230, '2021-10-15', '2026-10-14', null),
            $this->tank('25', '10302864', 'Breathing Apparatus', 'ECS', '2008-01-01', 11.6, '10 L', 345, 230, '2021-10-15', '2026-10-14', null),
            $this->tank('26', '10302833', 'Breathing Apparatus', 'ECS', '2008-01-01', 11.9, '10 L', 345, 230, '2021-09-29', '2026-09-28', null),
            $this->tank('28', 'GQBO59', 'Breathing Apparatus', 'ECS', '2013-01-01', 11.9, '10 L', 348, 232, '2021-10-15', '2026-10-14', null),
            $this->tank('29', 'GQBO73', 'Breathing Apparatus', 'ECS', '2013-01-01', 11.9, '10 L', 348, 232, '2021-10-15', '2026-10-14', null),
            $this->tank('30', 'GQBO81', 'Breathing Apparatus', 'ECS', '2013-01-01', 11.9, '10 L', 348, 232, '2021-09-29', '2026-09-28', null),
            $this->tank('31', 'GQBO96', 'Breathing Apparatus', 'ECS', '2014-01-01', 12.2, '10 L', 348, 232, '2021-09-29', '2026-09-28', null),
            $this->tank('32', 'LUR 184 UT', 'Polaris TS 50', 'ECS', '2015-09-01', 8.4, '7 L', 348, 232, '2021-09-29', '2026-09-28', null, 'Piscine Merl'),
            $this->tank('M02', '93AA59432CA', 'SPIROTECHNIQUE', 'ROTH', '1993-05-01', 14.1, '12.1 L', 300, 200, '2021-09-29', '2026-09-28', null),
            $this->tank('M03', '93AA43993CA', 'SPIROTECHNIQUE', 'ROTH', '1993-03-01', 14.2, '12.1 L', 300, 200, '2021-10-15', '2026-10-14', null),
            $this->tank('M04', '93AA59456CA', 'SPIROTECHNIQUE', 'ROTH', '1993-05-01', 14.1, '12.1 L', 300, 200, '2021-09-29', '2026-09-28', null),
            $this->tank('M05', '93AA59415CA', 'SPIROTECHNIQUE', 'ROTH', '1993-05-01', 14.2, '12.1 L', 300, 200, '2021-09-29', '2026-09-28', null),
            $this->tank('M06', '93AA59458CA', 'SPIROTECHNIQUE', 'ROTH', '1993-05-01', 14.2, '12.1 L', 300, 200, '2021-09-29', '2026-09-28', null),
        ];
    }

    // ── Bloc Nitrox (7 active) ────────────────────────────────────────

    private function blocNitrox(): array
    {
        return [
            $this->nitrox('33', 'LLS 005 UT', 'Polaris TS 51', 'ECS', '2015-04-01', null, '10 L', 348, 220, '2021-10-15', '2026-10-14', null),
            $this->nitrox('34', 'LLS 006 UT', 'Polaris TS 52', 'ECS', '2015-04-01', null, '10 L', 348, 220, '2021-09-29', '2026-09-28', null),
            $this->nitrox('35', 'LLS 015 UT', 'Polaris TS 53', 'ECS', '2015-04-01', null, '10 L', 348, 220, '2021-09-29', '2026-09-28', null),
            $this->nitrox('36', 'LLR 194 UT', 'Polaris TS 54', 'ECS', '2015-04-01', null, '10 L', 348, 220, '2021-10-15', '2026-10-14', null),
            $this->nitrox('37', 'LWB 155 UT', 'Polaris TS 55', 'ECS', '2015-06-01', null, '12 L', 348, 220, '2021-09-29', '2026-09-28', null),
            $this->nitrox('38', 'LWB 167 UT', 'Polaris TS 56', 'ECS', '2015-06-01', null, '12 L', 348, 220, '2021-09-29', '2026-09-28', null),
            $this->nitrox('M01', '93AA59488CA', 'SPIROTECHNIQUE', 'ROTH', '1993-05-01', 14.1, '12.1 L', 300, 220, '2021-10-15', '2026-10-14', null),
        ];
    }

    // ── Oxygen (2) ────────────────────────────────────────────────────

    private function oxygen(): array
    {
        return [
            ['name' => 'Bouteille O2 2L', 'short_number' => 'O2-1', 'type' => 'other', 'serial_number' => '428852', 'brand' => 'Air Liquide', 'volume' => '2 L', 'condition' => 'good', 'status' => 'available', 'is_loanable' => false, 'notes' => 'Oxygen — In the first aid kit'],
            ['name' => 'Bouteille O2 5L', 'short_number' => 'O2-2', 'type' => 'other', 'serial_number' => '399075', 'brand' => 'Air Liquide', 'volume' => '5 L', 'condition' => 'good', 'status' => 'available', 'is_loanable' => false, 'notes' => 'Oxygen — In the warehouse'],
        ];
    }

    // ── Tampons / Buffer tanks (4) ────────────────────────────────────

    private function tampons(): array
    {
        return [
            ['name' => 'Tampon 50L', 'short_number' => 'T1', 'type' => 'other', 'serial_number' => '12623130', 'brand' => 'Fischer Gase', 'manufacture_date' => '2015-04-10', 'weight_kg' => 77.1, 'volume' => '50 L', 'working_pressure_bar' => 300, 'last_retest_date' => '2015-04-10', 'next_retest_date' => '2025-04-07', 'condition' => 'good', 'status' => 'available', 'is_loanable' => false, 'notes' => 'Buffer tank'],
            ['name' => 'Tampon 50L', 'short_number' => 'T2', 'type' => 'other', 'serial_number' => '12623153', 'brand' => 'Fischer Gase', 'manufacture_date' => '2015-04-10', 'weight_kg' => 77.2, 'volume' => '50 L', 'working_pressure_bar' => 300, 'last_retest_date' => '2015-04-10', 'next_retest_date' => '2025-04-07', 'condition' => 'good', 'status' => 'available', 'is_loanable' => false, 'notes' => 'Buffer tank'],
            ['name' => 'Tampon 50L', 'short_number' => 'T3', 'type' => 'other', 'serial_number' => '12623135', 'brand' => 'Fischer Gase', 'manufacture_date' => '2015-04-10', 'weight_kg' => 77.1, 'volume' => '50 L', 'working_pressure_bar' => 300, 'last_retest_date' => '2015-04-10', 'next_retest_date' => '2025-04-07', 'condition' => 'good', 'status' => 'available', 'is_loanable' => false, 'notes' => 'Buffer tank'],
            ['name' => 'Tampon 50L', 'short_number' => 'T4', 'type' => 'other', 'serial_number' => '12523160', 'brand' => 'Fischer Gase', 'manufacture_date' => '2015-04-10', 'weight_kg' => 77.2, 'volume' => '50 L', 'working_pressure_bar' => 300, 'last_retest_date' => '2015-04-10', 'next_retest_date' => '2025-04-07', 'condition' => 'good', 'status' => 'available', 'is_loanable' => false, 'notes' => 'Buffer tank'],
        ];
    }

    // ── Détendeurs / Regulators (21 DIN + 1 octopus) ──────────────────

    private function regulators(): array
    {
        return [
            $this->reg('8', 'Aqua Lung', 'Titan', 'EN250 7092148', 'Aqualung A085508', '2007', 'octobre 2025'),
            $this->reg('9', 'Aqua Lung', 'Titan', 'EN250 7092105', 'Aqualung A055889', '2007', 'avril 2023'),
            $this->reg('10', 'Aqua Lung', 'Calypso', 'EN250 7081051', 'Aqualung A085543', null, 'octobre 2025'),
            $this->reg('11', 'Aqua Lung', 'Calypso', 'EN250 7081067', 'Aqualung A085544', null, 'octobre 2025'),
            $this->reg('12', 'Aqua Lung', 'Calypso', 'EN250 7081069', 'Aqualung A085546', null, 'avril 2023'),
            $this->reg('14', 'Aqua Lung', 'Legend', 'A087986', 'Aqualung A086644', '2010', 'avril 2023', 'Mano et direct system récupérés du n°2'),
            $this->reg('15', 'Mares', 'Rover R2S', 'BRS55349', 'Mares Loop BOL13381', '2021', 'octobre 2025', 'Achat 21/5/2021'),
            $this->reg('16', 'Mares', 'Rover R2S', 'BRS55351', 'Mares Loop BOL13369', '2021', 'avril 2023', 'Achat 21/5/2021'),
            $this->reg('17', 'Mares', 'Rover R2S', 'BRS55352', 'Mares Loop BOL13371', '2021', 'avril 2023', 'Achat 21/5/2021'),
            $this->reg('18', 'Mares', 'Rover R2S', 'BNS11747', 'Mares BNO13264', '2021', 'avril 2023', 'Achat 30/6/2021'),
            $this->reg('19', 'Mares', 'Rover R2S', 'BNS11748', 'Mares BNO12642', '2021', 'avril 2023', 'Achat 30/6/2021'),
            $this->reg('20', 'Mares', 'Rover R2S', 'BNS11770', 'Mares BNO12644', '2021', 'novembre 2025', 'Achat 30/6/2021'),
            $this->reg('21', 'Mares', 'Rover R2S', 'BNS11818', 'Mares BNO13262', '2021', 'avril 2023', 'Achat 30/6/2021'),
            $this->reg('22', 'Mares', 'Rover R2S', 'BRS55368', 'Mares BNO13263', '2021', 'avril 2023', 'Achat 30/6/2021'),
            $this->reg('36', 'Mares', 'Rover R2S', 'BNR15608', 'Mares BNK32085', '2025', null, 'Achat 10/1/2025 — embout enfant'),
            $this->reg('41', 'Mares', '15X', 'QD13547', 'Mares BNO14246', '2022', null, '2ème 1er étage. Achat 15/6/2022'),
            $this->reg('42', 'Mares', '15X', 'QD13554', 'Mares BNO14243', '2022', null, '2ème 1er étage. Achat 27/7/2022'),
            $this->reg('43', 'Mares', 'Rover R2S', 'BNS12579', 'Mares BNK32087', '2025', null, 'Achat 10/1/2025'),
            $this->reg('44', 'Mares', 'Rover R2S', 'BNR15609', 'Mares BNK32083', '2025', null, 'Achat 10/1/2025'),
            $this->reg('45', 'Mares', 'Rover R2S', 'BNR15557', 'Mares BNK32084', '2025', null, 'Achat 10/1/2025 — embout enfant'),
            $this->reg('47', 'Mares', 'Rover R2S', 'BNR15610', 'Mares BNK32082', '2025', null, 'Achat 10/1/2025 — embout enfant'),
        ];
    }

    // ── Gilets / BCDs (26 active) ─────────────────────────────────────

    private function bcds(): array
    {
        return [
            // L (3)
            $this->bcd('L5', 'Aqualung', null, 'L', '2014'),
            $this->bcd('L6', 'Spiro', 'Octopus SBC', 'L', '1994', 'Jaune'),
            $this->bcd('L37', 'Mares', 'Rover DC', 'L', '2025'),
            // M (9)
            $this->bcd('M1', 'Scubapro', 'Top 100', 'M', '1999'),
            $this->bcd('M2', 'Scubapro', 'Top 100', 'M', '1999'),
            $this->bcd('M7', 'Scubapro', 'Top 100', 'M', '2001', null, 'Nouvelle sangle installée le 16/6/2022'),
            $this->bcd('M8', 'Aqualung', null, 'M', '2014'),
            $this->bcd('M9', 'Aqualung', null, 'M', '2014'),
            $this->bcd('M10', 'Spiro', 'Octopus SBC', 'M', '1994', 'Rouge'),
            $this->bcd('M12', 'Aqualung', null, 'M', '2014'),
            $this->bcd('M20', 'Scubapro', 'Slide 500', 'M', '2001', null, 'Missing the tank belt ring'),
            $this->bcd('M38', 'Mares', 'Rover DC', 'M', '2025'),
            // S (7)
            $this->bcd('S14', 'Cressi', null, 'S', '2014'),
            $this->bcd('S15', 'Cressi', null, 'S', '2007'),
            $this->bcd('S16', 'Cressi', null, 'S', '2007'),
            $this->bcd('S17', 'Cressi', null, 'S', '2007'),
            $this->bcd('S35', 'Mares', 'Rover DC', 'S', '2023'),
            $this->bcd('S36', 'Mares', 'Rover DC', 'S', '2023'),
            $this->bcd('S39', 'Mares', 'Rover DC', 'S', '2025'),
            // XS (4)
            $this->bcd('XS13', 'Aqualung', null, 'XS', '2014'),
            $this->bcd('XS30', 'SCD', '100', 'XS', '2020'),
            $this->bcd('XS31', 'SCD', '100', 'XS', '2020'),
            $this->bcd('XS34', 'Mares', 'Vector Origin', 'XS', null, null, 'Anciennement numéroté XS19'),
            // XXXS (2)
            $this->bcd('XXXS32', 'Mares', 'Scuba Ranger', 'XXXS', '2021'),
            $this->bcd('XXXS33', 'Mares', 'Scuba Ranger', 'XXXS', '2021'),
        ];
    }

    // ── Clés Local / Keys (6) ─────────────────────────────────────────

    private function keys(): array
    {
        return [
            $this->key('1', 'Yves'),
            $this->key('2', 'Marie-Jo'),
            $this->key('3', 'Pascale'),
            $this->key('4', 'Nico/Laura'),
            $this->key('5', 'Pietro'),
            $this->key('6', 'Eduardo'),
        ];
    }

    // ── Helper builders ───────────────────────────────────────────────

    private function tank(string $shortNum, string $serial, string $brand, string $mfr, string $mfgDate, ?float $weight, string $volume, int $testBar, int $workBar, string $lastRetest, string $nextRetest, ?string $notes, string $location = 'Entrepôt'): array
    {
        return [
            'name' => 'Bloc Air '.$volume,
            'short_number' => $shortNum,
            'type' => 'tank',
            'serial_number' => $serial,
            'brand' => $brand,
            'manufacturer' => $mfr,
            'threading' => '25x200',
            'manufacture_date' => $mfgDate,
            'weight_kg' => $weight,
            'volume' => $volume,
            'material' => 'Acier',
            'test_pressure_bar' => $testBar,
            'working_pressure_bar' => $workBar,
            'last_retest_date' => $lastRetest,
            'next_retest_date' => $nextRetest,
            'last_inventory_date' => '2021-05-19',
            'condition' => 'good',
            'status' => 'available',
            'location' => $location,
            'notes' => $notes,
        ];
    }

    private function nitrox(string $shortNum, string $serial, string $brand, string $mfr, string $mfgDate, ?float $weight, string $volume, int $testBar, int $workBar, string $lastRetest, string $nextRetest, ?string $notes, string $location = 'Entrepôt'): array
    {
        return [
            'name' => 'Bloc Nitrox '.$volume,
            'short_number' => $shortNum,
            'type' => 'tank',
            'serial_number' => $serial,
            'brand' => $brand,
            'manufacturer' => $mfr,
            'threading' => '25x200',
            'manufacture_date' => $mfgDate,
            'weight_kg' => $weight,
            'volume' => $volume,
            'material' => 'Acier',
            'test_pressure_bar' => $testBar,
            'working_pressure_bar' => $workBar,
            'last_retest_date' => $lastRetest,
            'next_retest_date' => $nextRetest,
            'last_inventory_date' => '2021-05-19',
            'condition' => 'good',
            'status' => 'available',
            'location' => $location,
            'notes' => $notes,
        ];
    }

    private function reg(string $shortNum, string $brand, string $model, string $serial, string $octopus, ?string $year, ?string $revision, ?string $notes = null): array
    {
        return [
            'name' => $brand.' '.$model,
            'short_number' => $shortNum,
            'type' => 'regulator',
            'serial_number' => $serial,
            'brand' => $brand,
            'manufacture_date' => $year ? $year.'-01-01' : null,
            'condition' => 'good',
            'status' => 'available',
            'notes' => implode('. ', array_filter(["Octopus: $octopus", $revision ? "Révision: $revision" : null, $notes])),
        ];
    }

    private function bcd(string $shortNum, string $brand, ?string $model, string $size, ?string $year, ?string $color = null, ?string $notes = null): array
    {
        $label = $model ? "$brand $model" : $brand;

        return [
            'name' => "Gilet $size — $label",
            'short_number' => $shortNum,
            'type' => 'bcd',
            'brand' => $brand,
            'manufacture_date' => $year ? $year.'-01-01' : null,
            'volume' => $size,
            'condition' => 'good',
            'status' => 'available',
            'notes' => implode('. ', array_filter([$color ? "Couleur: $color" : null, $notes])),
        ];
    }

    private function key(string $number, string $holder): array
    {
        return [
            'name' => "Clé Local n°$number",
            'short_number' => "K$number",
            'type' => 'other',
            'condition' => 'good',
            'status' => 'available',
            'is_loanable' => true,
            'notes' => "Détenteur: $holder",
        ];
    }
}
