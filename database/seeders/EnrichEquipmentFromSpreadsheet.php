<?php

namespace Database\Seeders;

use App\Models\Equipment;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class EnrichEquipmentFromSpreadsheet extends Seeder
{
    public function run(): void
    {
        $url = 'https://docs.google.com/spreadsheets/d/1oD2-YbwTgqT3GBrfGKeUv9uq-IpE7O6oqfdkAvD_lFM/export?format=csv&gid=2131456927';
        $csv = Http::get($url)->body();
        $lines = array_map('str_getcsv', explode("\n", $csv));
        $updated = 0;

        foreach ($lines as $row) {
            $type = trim($row[0] ?? '');
            $num = trim($row[1] ?? '');
            $serial = trim($row[2] ?? '');

            if (! $num || ! $type || str_contains($type, 'Total') || str_contains($type, 'Hors Inventaire')) {
                continue;
            }
            if (str_contains($type, 'HI')) {
                continue; // Skip "Hors Inventaire" items
            }

            // Match equipment by short_number or name containing the number
            $eq = Equipment::where('short_number', $num)->first();
            if (! $eq) {
                $eq = Equipment::where('name', 'ILIKE', "%Bloc%{$num}%")->first();
            }
            if (! $eq) {
                $eq = Equipment::where('name', 'ILIKE', "%{$num}%")->where('type', 'tank')->first();
            }
            if (! $eq) {
                continue;
            }

            $data = array_filter([
                'serial_number' => $serial ?: null,
                'brand' => trim($row[3] ?? '') ?: null,
                'manufacturer' => trim($row[4] ?? '') ?: null,
                'threading' => trim($row[5] ?? '') ?: null,
                'manufacture_date' => $this->parseDate($row[6] ?? ''),
                'weight_kg' => $this->parseDecimal($row[7] ?? ''),
                'volume' => trim($row[8] ?? '') ?: null,
                'material' => trim($row[9] ?? '') ?: null,
                'test_pressure_bar' => $this->parseDecimal($row[10] ?? ''),
                'working_pressure_bar' => $this->parseDecimal($row[11] ?? ''),
                'last_retest_date' => $this->parseDate($row[13] ?? ''),
                'next_retest_date' => $this->parseDate($row[14] ?? ''),
                'last_inventory_date' => $this->parseDate($row[15] ?? ''),
                'notes' => trim($row[16] ?? '') ?: null,
            ], fn ($v) => $v !== null);

            if ($data) {
                $eq->update($data);
                $this->command->info("  #{$num}: {$eq->name} — updated ".count($data).' fields');
                $updated++;
            }
        }

        $this->command->info("{$updated} equipment items enriched from spreadsheet.");
    }

    private function parseDate(string $val): ?string
    {
        $val = trim($val);
        if (! $val) {
            return null;
        }

        try {
            // Handle French date formats
            $val = str_replace(
                ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'],
                ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                $val
            );

            return Carbon::parse($val)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDecimal(string $val): ?float
    {
        $val = trim($val);
        if (! $val) {
            return null;
        }
        $val = preg_replace('/[^\d,.]/', '', str_replace(',', '.', $val));

        return $val ? (float) $val : null;
    }
}
