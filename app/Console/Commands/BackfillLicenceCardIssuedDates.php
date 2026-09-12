<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Helpers\PdfMetadata;
use App\Models\Document;
use App\Models\Federation;
use App\Models\MemberLicence;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-off backfill for FLASSA licences that predate the Licence Scans
 * intake: reads the member's uploaded licence_card PDF's own CreationDate
 * (see PdfMetadata) and stores it as card_issued_at on the matching
 * member_licences row.
 */
class BackfillLicenceCardIssuedDates extends Command
{
    protected $signature = 'licences:backfill-issued-dates';

    protected $description = "Read each member's FLASSA licence_card PDF creation date into member_licences.card_issued_at";

    public function handle(): int
    {
        $flassaId = Federation::where('acronym', 'FLASSA')->value('id');
        if (! $flassaId) {
            $this->warn('No FLASSA federation configured — nothing to do.');

            return self::SUCCESS;
        }

        $updated = 0;
        $skipped = 0;

        Document::where('category', 'licence_card')
            ->where('is_current', true)
            ->each(function (Document $document) use ($flassaId, &$updated, &$skipped): void {
                $licence = MemberLicence::where('user_id', $document->user_id)
                    ->where('federation_id', $flassaId)
                    ->whereNull('card_issued_at')
                    ->first();

                if (! $licence) {
                    $skipped++;

                    return;
                }

                $path = str_starts_with($document->file_path, 'private/')
                    ? substr($document->file_path, 8)
                    : $document->file_path;

                if (! Storage::disk('local')->exists($path)) {
                    $skipped++;

                    return;
                }

                $issuedAt = PdfMetadata::creationDate(Storage::disk('local')->path($path));
                if (! $issuedAt) {
                    $skipped++;

                    return;
                }

                $licence->update(['card_issued_at' => $issuedAt]);
                $updated++;
            });

        $this->info("{$updated} licence(s) updated, {$skipped} skipped.");

        return self::SUCCESS;
    }
}
