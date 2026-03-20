<?php

namespace Database\Seeders;

use App\Models\Equipment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CepEquipmentSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing equipment
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
        );
    }

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
            $this->tank('20', '86AA524CA', 'SPIROTECHNIQUE', 'ROTH', '1985-01-01', 10.1, '9 L', 264, 176, '2021-09-29', '2026-09-28', 'Bi 2x9 séparé en 2 blocs individuel petit gabarit'),
            $this->tank('21', '85AA448CA', 'SPIROTECHNIQUE', 'ROTH', '1985-01-01', 10.1, '9 L', 264, 176, '2021-10-15', '2026-10-14', 'Bi 2x9 séparé en 2 blocs individuel petit gabarit'),
            $this->tank('22', '86AA481CA', 'SPIROTECHNIQUE', 'ROTH', '1985-01-01', 10.1, '9 L', 264, 176, '2021-09-29', '2026-09-28', 'Bi 2x9 séparé. Problème de robinetterie'),
            $this->tank('23', '86AA488CA', 'SPIROTECHNIQUE', 'ROTH', '1985-01-01', 10.1, '9 L', 264, 176, '2021-10-15', '2026-10-14', 'Bi 2x9 séparé en 2 blocs individuel petit gabarit'),
            $this->tank('24', '10302863', 'Breathing Apparatus', 'ECS', '2008-01-01', 11.9, '10 L', 345, 230, '2021-10-15', '2026-10-14', null),
            $this->tank('25', '10302864', 'Breathing Apparatus', 'ECS', '2008-01-01', 11.6, '10 L', 345, 230, '2021-10-15', '2026-10-14', null),
            $this->tank('26', '10302833', 'Breathing Apparatus', 'ECS', '2008-01-01', 11.9, '10 L', 345, 230, '2021-09-29', '2026-09-28', null),
            $this->tank('28', 'GQBO59', 'Breathing Apparatus', 'ECS', '2013-01-01', 11.9, '10 L', 348, 232, '2021-10-15', '2026-10-14', null),
            $this->tank('29', 'GQBO73', 'Breathing Apparatus', 'ECS', '2013-01-01', 11.9, '10 L', 348, 232, '2021-10-15', '2026-10-14', null),
            $this->tank('30', 'GQBO81', 'Breathing Apparatus', 'ECS', '2013-01-01', 11.9, '10 L', 348, 232, '2021-09-29', '2026-09-28', null),
            $this->tank('31', 'GQBO96', 'Breathing Apparatus', 'ECS', '2014-01-01', 12.2, '10 L', 348, 232, '2021-09-29', '2026-09-28', null),
            $this->tank('32', 'LUR 184 UT', 'Polaris TS 50', 'ECS', '2015-09-01', 8.4, '7 L', 348, 232, '2021-09-29', '2026-09-28', null),
            $this->tank('M02', '93AA59432CA', 'SPIROTECHNIQUE', 'ROTH', '1993-05-01', 14.1, '12.1 L', 300, 200, '2021-09-29', '2026-09-28', null),
            $this->tank('M03', '93AA43993CA', 'SPIROTECHNIQUE', 'ROTH', '1993-03-01', 14.2, '12.1 L', 300, 200, '2021-10-15', '2026-10-14', null),
            $this->tank('M04', '93AA59456CA', 'SPIROTECHNIQUE', 'ROTH', '1993-05-01', 14.1, '12.1 L', 300, 200, '2021-09-29', '2026-09-28', null),
            $this->tank('M05', '93AA59415CA', 'SPIROTECHNIQUE', 'ROTH', '1993-05-01', 14.2, '12.1 L', 300, 200, '2021-09-29', '2026-09-28', null),
            $this->tank('M06', '93AA59458CA', 'SPIROTECHNIQUE', 'ROTH', '1993-05-01', 14.2, '12.1 L', 300, 200, '2021-09-29', '2026-09-28', null),
        ];
    }

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

    private function oxygen(): array
    {
        return [
            ['club_id' => null, 'name' => 'Bouteille O2 2L', 'type' => 'oxygen', 'serial_number' => '428852', 'brand' => 'Air Liquide', 'manufacturer' => null, 'threading' => null, 'manufacture_date' => null, 'weight_kg' => null, 'volume' => '2 L', 'material' => null, 'test_pressure_bar' => null, 'working_pressure_bar' => null, 'last_retest_date' => null, 'next_retest_date' => null, 'last_inventory_date' => null, 'condition' => 'good', 'status' => 'available', 'notes' => 'In the first aid kit'],
            ['club_id' => null, 'name' => 'Bouteille O2 5L', 'type' => 'oxygen', 'serial_number' => '399075', 'brand' => 'Air Liquide', 'manufacturer' => null, 'threading' => null, 'manufacture_date' => null, 'weight_kg' => null, 'volume' => '5 L', 'material' => null, 'test_pressure_bar' => null, 'working_pressure_bar' => null, 'last_retest_date' => null, 'next_retest_date' => null, 'last_inventory_date' => null, 'condition' => 'good', 'status' => 'available', 'notes' => 'In the warehouse'],
        ];
    }

    private function tampons(): array
    {
        return [
            ['club_id' => null, 'name' => 'Tampon 50L', 'type' => 'buffer_tank', 'serial_number' => '12623130', 'brand' => 'Fischer Gase', 'manufacturer' => null, 'threading' => null, 'manufacture_date' => '2015-04-10', 'weight_kg' => 77.1, 'volume' => '50 L', 'material' => null, 'test_pressure_bar' => null, 'working_pressure_bar' => 300, 'last_retest_date' => '2015-04-10', 'next_retest_date' => '2025-04-07', 'last_inventory_date' => null, 'condition' => 'good', 'status' => 'available', 'notes' => null],
            ['club_id' => null, 'name' => 'Tampon 50L', 'type' => 'buffer_tank', 'serial_number' => '12623153', 'brand' => 'Fischer Gase', 'manufacturer' => null, 'threading' => null, 'manufacture_date' => '2015-04-10', 'weight_kg' => 77.2, 'volume' => '50 L', 'material' => null, 'test_pressure_bar' => null, 'working_pressure_bar' => 300, 'last_retest_date' => '2015-04-10', 'next_retest_date' => '2025-04-07', 'last_inventory_date' => null, 'condition' => 'good', 'status' => 'available', 'notes' => null],
            ['club_id' => null, 'name' => 'Tampon 50L', 'type' => 'buffer_tank', 'serial_number' => '12623135', 'brand' => 'Fischer Gase', 'manufacturer' => null, 'threading' => null, 'manufacture_date' => '2015-04-10', 'weight_kg' => 77.1, 'volume' => '50 L', 'material' => null, 'test_pressure_bar' => null, 'working_pressure_bar' => 300, 'last_retest_date' => '2015-04-10', 'next_retest_date' => '2025-04-07', 'last_inventory_date' => null, 'condition' => 'good', 'status' => 'available', 'notes' => null],
            ['club_id' => null, 'name' => 'Tampon 50L', 'type' => 'buffer_tank', 'serial_number' => '12523160', 'brand' => 'Fischer Gase', 'manufacturer' => null, 'threading' => null, 'manufacture_date' => '2015-04-10', 'weight_kg' => 77.2, 'volume' => '50 L', 'material' => null, 'test_pressure_bar' => null, 'working_pressure_bar' => 300, 'last_retest_date' => '2015-04-10', 'next_retest_date' => '2025-04-07', 'last_inventory_date' => null, 'condition' => 'good', 'status' => 'available', 'notes' => null],
        ];
    }

    private function tank(string $clubId, string $serial, string $brand, string $mfr, string $mfgDate, ?float $weight, string $volume, int $testBar, int $workBar, string $lastRetest, string $nextRetest, ?string $notes): array
    {
        return [
            'club_id' => $clubId,
            'name' => 'Bloc Air '.$volume,
            'type' => 'tank_air',
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
            'notes' => $notes,
        ];
    }

    private function nitrox(string $clubId, string $serial, string $brand, string $mfr, string $mfgDate, ?float $weight, string $volume, int $testBar, int $workBar, string $lastRetest, string $nextRetest, ?string $notes): array
    {
        return [
            'club_id' => $clubId,
            'name' => 'Bloc Nitrox '.$volume,
            'type' => 'tank_nitrox',
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
            'notes' => $notes,
        ];
    }
}
