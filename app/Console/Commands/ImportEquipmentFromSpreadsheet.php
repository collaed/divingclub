<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Equipment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * One-time import: regulators and BCDs exist in the club's "Inventaire CEP"
 * spreadsheet (sheets "Détendeurs" and "Jackets") but were never entered
 * into Equipment — only tanks were. Creates the missing rows, with
 * short_number already set so the gear-lending shorthand works immediately.
 *
 * Rows under a "Hors Inventaire" (retired) section, and rows with no CEP
 * number to key on (e.g. the lone spare octopus, the harnesses), are
 * skipped — they can be added by hand if ever needed.
 */
class ImportEquipmentFromSpreadsheet extends Command
{
    protected $signature = 'app:import-equipment-from-spreadsheet {--dry-run : Preview without saving}';

    protected $description = 'Import regulators and BCDs from the Inventaire CEP spreadsheet into Equipment';

    private const SHEET_ID = '1oD2-YbwTgqT3GBrfGKeUv9uq-IpE7O6oqfdkAvD_lFM';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $toCreate = [
            ...$this->parseRegulators(),
            ...$this->parseBcds(),
        ];

        $rows = [];
        $skippedCollisions = [];
        foreach ($toCreate as $item) {
            $collision = Equipment::where('type', $item['type'])
                ->whereRaw('LOWER(short_number) = ?', [strtolower($item['short_number'])])
                ->first();
            if ($collision !== null) {
                $skippedCollisions[] = [$item['type'], $item['short_number'], "would collide with #{$collision->id}"];

                continue;
            }
            $rows[] = $item;
        }

        $this->table(['Type', 'short_number', 'Name', 'Brand', 'Size'], array_map(
            fn ($r) => [$r['type'], $r['short_number'], $r['name'], $r['brand'] ?? '', $r['size'] ?? ''],
            $rows
        ));

        if ($skippedCollisions !== []) {
            $this->warn('Skipped (short_number already taken):');
            $this->table(['Type', 'short_number', 'Reason'], $skippedCollisions);
        }

        if ($dryRun) {
            $this->info(count($rows).' would be created (dry run — nothing saved).');

            return self::SUCCESS;
        }

        foreach ($rows as $item) {
            Equipment::create($item);
        }

        $this->info(count($rows).' equipment row(s) created.');

        return self::SUCCESS;
    }

    /** @return list<array{name: string, type: string, short_number: string, brand: ?string, serial_number: ?string, is_loanable: bool, status: string}> */
    private function parseRegulators(): array
    {
        $rows = $this->fetchSheet('Détendeurs');
        $items = [];
        $retired = false;

        foreach ($rows as $row) {
            $type = trim($row[0] ?? '');
            $no = trim($row[1] ?? '');

            if (str_contains($type, 'Hors Inventaire')) {
                $retired = true;

                continue;
            }
            if ($type === '' || $no === '' || ! ctype_digit($no) || $retired) {
                continue;
            }

            $brand = trim($row[2] ?? '');
            $model = trim($row[3] ?? '');
            $serial = trim($row[4] ?? '');

            $items[] = [
                'name' => trim("Détendeur {$type} {$brand} {$model}"),
                'type' => 'regulator',
                'short_number' => $no,
                'brand' => $brand ?: null,
                'serial_number' => $serial ?: null,
                'is_loanable' => true,
                'status' => 'available',
            ];
        }

        return $items;
    }

    /** @return list<array{name: string, type: string, short_number: string, brand: ?string, size: ?string, is_loanable: bool, status: string}> */
    private function parseBcds(): array
    {
        $rows = $this->fetchSheet('Jackets');
        $items = [];
        $retired = false;

        foreach ($rows as $row) {
            $label = trim($row[0] ?? '');
            $code = trim($row[1] ?? '');

            if (str_contains($label, 'Hors Inventaire')) {
                $retired = true;

                continue;
            }
            if ($label === '' || $code === '' || $retired) {
                continue;
            }
            if (! str_starts_with(strtolower($label), 'gilet ')) {
                // "Harnais" and section header rows ("GILETS M", ...) — not a BCD row.
                continue;
            }

            $size = trim(substr($label, strlen('Gilet ')));
            $brand = trim($row[2] ?? '');
            $modelType = trim($row[3] ?? '');

            $items[] = [
                'name' => trim("Gilet {$size} {$brand} {$modelType}"),
                'type' => 'bcd',
                'short_number' => $code,
                'brand' => $brand ?: null,
                'size' => $size !== '' ? strtolower($size) : null,
                'is_loanable' => true,
                'status' => 'available',
            ];
        }

        return $items;
    }

    /** @return list<list<string>> */
    private function fetchSheet(string $sheetName): array
    {
        $url = 'https://docs.google.com/spreadsheets/d/'.self::SHEET_ID.'/gviz/tq?tqx=out:csv&sheet='.rawurlencode($sheetName);
        $csv = Http::get($url)->body();
        $rows = array_map('str_getcsv', explode("\n", trim($csv)));

        return array_slice($rows, 1); // drop header row
    }
}
