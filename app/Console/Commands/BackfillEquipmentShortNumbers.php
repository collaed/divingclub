<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Equipment;
use Illuminate\Console\Command;

/**
 * One-time backfill: the quick-entry gear shorthand (t14, tM4, ...) matches
 * against Equipment::short_number, which was never populated for existing
 * inventory — only the tag baked into the name (e.g. "Bloc Air #M02") has
 * it. Extracts that tag into short_number wherever it's unambiguously a
 * short code, not a serial number (e.g. skips "Tampon #12623153").
 */
class BackfillEquipmentShortNumbers extends Command
{
    protected $signature = 'app:backfill-equipment-short-numbers {--dry-run : Preview without saving}';

    protected $description = 'Backfill Equipment::short_number from the "#code" tag in the name, for rows that have none yet';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = Equipment::whereNull('short_number')
            ->where('name', 'like', '%#%')
            ->get();

        $toUpdate = [];
        $skipped = [];

        foreach ($candidates as $equipment) {
            $code = $this->extractCode($equipment->name);
            if ($code === null) {
                $skipped[] = [$equipment->id, $equipment->name, 'no short code found'];

                continue;
            }

            $collision = Equipment::where('type', $equipment->type)
                ->whereRaw('LOWER(short_number) = ?', [strtolower($code)])
                ->first();
            if ($collision !== null) {
                $skipped[] = [$equipment->id, $equipment->name, "would collide with #{$collision->id} ({$collision->name})"];

                continue;
            }

            $toUpdate[] = [$equipment, $code];
        }

        $this->table(['ID', 'Name', 'short_number ->'], array_map(fn ($row) => [$row[0]->id, $row[0]->name, $row[1]], $toUpdate));

        if ($skipped !== []) {
            $this->warn('Skipped (left untouched):');
            $this->table(['ID', 'Name', 'Reason'], $skipped);
        }

        if ($dryRun) {
            $this->info(count($toUpdate).' would be updated (dry run — nothing saved).');

            return self::SUCCESS;
        }

        foreach ($toUpdate as [$equipment, $code]) {
            $equipment->update(['short_number' => $code]);
        }

        $this->info(count($toUpdate).' equipment row(s) updated.');

        return self::SUCCESS;
    }

    /**
     * The tag after the last "#" counts as a short code only when it's an
     * optional 1-2 letter prefix plus up to 3 digits — long digit runs are
     * serial numbers (e.g. "Tampon #12623153"), not lending codes.
     */
    private function extractCode(string $name): ?string
    {
        if (! preg_match('/#([A-Za-z]{0,2}\d{1,3})\s*$/', $name, $m)) {
            return null;
        }

        return $m[1];
    }
}
