<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LibraryFile;
use App\Services\ComptesRendusActionExtractionService;
use App\Services\ScheduleHeartbeat;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Picks one not-yet-processed compte-rendu, newest first, and turns it into
 * kanban cards. Deliberately one document per run (scheduled every 3 hours,
 * see routes/console.php) — a slow trickle so a human can review extraction
 * quality on the board as it fills, rather than being handed 185 documents'
 * worth of cards at once. Idempotent: a document is only ever picked up once
 * `kanban_processed_at` is set, whether that run produced cards or not.
 */
class ExtractComptesRendusActions extends Command
{
    protected $signature = 'compte-rendus:extract-actions';

    protected $description = 'Extract action items from the next unprocessed compte-rendu onto the kanban board';

    /** Document-name patterns that identify a compte-rendu, not a false positive like a bank statement. */
    private const NAME_PATTERNS = ['CR %', '%compte rendu%', '%compte-rendu%'];

    public function handle(ComptesRendusActionExtractionService $extractor): int
    {
        $op = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';
        $file = LibraryFile::query()
            ->whereNull('kanban_processed_at')
            ->where(function ($q) use ($op): void {
                foreach (self::NAME_PATTERNS as $pattern) {
                    $q->orWhere('original_name', $op, $pattern);
                }
            })
            ->orderByDesc('created_at')
            ->first();

        if (! $file) {
            $this->info('No unprocessed compte-rendu left.');
            ScheduleHeartbeat::beat('compte-rendus-extraction');

            return self::SUCCESS;
        }

        $text = $extractor->textFor($file);
        if ($text === null || mb_strlen(trim($text)) < 50) {
            $file->update(['kanban_processed_at' => now(), 'kanban_skip_reason' => 'Could not extract text (unsupported format or missing file).']);
            $this->info("Skipped {$file->original_name}: no extractable text.");
            ScheduleHeartbeat::beat('compte-rendus-extraction');

            return self::SUCCESS;
        }

        $actions = $extractor->extractActions($text, $file->original_name);
        if ($actions === null) {
            // Transient failure (API down, no credentials) — leave unprocessed for the next run.
            Log::warning('Compte-rendu action extraction: AI call failed, will retry', ['file' => $file->original_name]);
            ScheduleHeartbeat::fail('compte-rendus-extraction', "Extraction failed for {$file->original_name} — will retry next run.");

            return self::FAILURE;
        }

        if ($actions !== []) {
            $saved = $extractor->save($actions, $file, $this->documentDate($file));
            if (! $saved) {
                // The remote board refused the push — retry next run rather than losing the document.
                Log::warning('Compte-rendu action extraction: could not save cards, will retry', ['file' => $file->original_name]);
                ScheduleHeartbeat::fail('compte-rendus-extraction', "Could not save cards for {$file->original_name} — will retry next run.");

                return self::FAILURE;
            }
        }

        $file->update(['kanban_processed_at' => now(), 'kanban_skip_reason' => $actions === [] ? 'No action items found.' : null]);
        $this->info("{$file->original_name}: ".count($actions).' action(s).');
        ScheduleHeartbeat::beat('compte-rendus-extraction');

        return self::SUCCESS;
    }

    /**
     * Best-effort date from the filename (several conventions are in use:
     * "CR bureau 14-12-2016", "COMPTE RENDU 07.02.17", "CR CEP - 03-09-2026").
     * Falls back to the file's upload date.
     */
    private function documentDate(LibraryFile $file): ?Carbon
    {
        if (preg_match('/(\d{1,2})[.\-](\d{1,2})[.\-](\d{2,4})/', $file->original_name, $m)) {
            $year = strlen($m[3]) === 2 ? (int) ('20'.$m[3]) : (int) $m[3];
            try {
                return Carbon::createFromDate($year, (int) $m[2], (int) $m[1])->startOfDay();
            } catch (\Throwable) {
                // Fall through to the upload date below.
            }
        }

        return $file->created_at?->copy();
    }
}
