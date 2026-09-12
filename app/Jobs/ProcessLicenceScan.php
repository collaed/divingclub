<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Helpers\FlassaLicenceTextParser;
use App\Helpers\PdfMetadata;
use App\Models\LicenceScan;
use App\Models\MemberLicence;
use App\Models\User;
use App\Services\CloudflareVisionOcrService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Renders a licence scan's first page to a lightweight image (for display),
 * and reads the holder name / licence number / year off the scan — for a
 * FLASSA card that's a straight text extraction (it's a generated PDF, not a
 * photo), falling back to Cloudflare Workers AI's vision model for anything
 * that isn't. Either applies the result straight to the one
 * exactly-matching member, or leaves it for a bureau member to assign by
 * hand.
 */
class ProcessLicenceScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $licenceScanId) {}

    public function handle(CloudflareVisionOcrService $ocr): void
    {
        $scan = LicenceScan::find($this->licenceScanId);
        if (! $scan) {
            return;
        }

        $sourcePath = Storage::disk('local')->path($scan->file_path);
        if (! file_exists($sourcePath)) {
            $scan->update(['status' => 'failed', 'extraction_error' => 'Uploaded file is missing on disk.']);

            return;
        }

        $imagePath = $this->renderFirstPage($sourcePath);
        if (! $imagePath) {
            $scan->update(['status' => 'failed', 'extraction_error' => 'Could not render the file to an image.']);

            return;
        }

        $imageDisk = 'licence-scans/'.$scan->id.'.png';
        Storage::disk('local')->put($imageDisk, file_get_contents($imagePath));
        @unlink($imagePath);
        $scan->update(['image_path' => $imageDisk]);

        $fields = $this->extractFromText($sourcePath, $scan->federation->acronym)
            ?? $ocr->extractLicenceFields(Storage::disk('local')->path($imageDisk))
            ?? ['name' => null, 'licence_number' => null, 'year' => null];

        $scan->update([
            'extracted_name' => $fields['name'] ?? null,
            'extracted_number' => $fields['licence_number'] ?? null,
            'extracted_year' => $fields['year'] ?? null,
        ]);

        $match = $fields['name'] ? $this->findExactMatch($fields['name']) : null;
        if (! $match) {
            $scan->update(['status' => 'needs_review', 'extraction_error' => $fields['name'] ? null : 'Name extraction failed — please enter it manually.']);

            return;
        }

        MemberLicence::updateOrCreate(
            ['user_id' => $match->id, 'federation_id' => $scan->federation_id],
            [
                'licence_number' => $fields['licence_number'],
                'season' => $fields['year'],
                'scan_image_path' => $imageDisk,
                'card_issued_at' => $this->extractCardIssuedAt($sourcePath, $scan->federation->acronym),
            ],
        );

        $scan->update(['status' => 'applied', 'matched_user_id' => $match->id]);

        Log::info("Licence scan #{$scan->id} auto-applied to user #{$match->id}");
    }

    /**
     * Returns null for any non-FLASSA federation, a non-PDF upload, or an
     * actual scanned image with no text layer, so the caller falls back to
     * the vision model.
     *
     * @return array{name: string, licence_number: string, year: string}|null
     */
    private function extractFromText(string $sourcePath, ?string $federationAcronym): ?array
    {
        if ($federationAcronym !== 'FLASSA' || ! str_contains(mime_content_type($sourcePath) ?: '', 'pdf')) {
            return null;
        }

        $text = shell_exec('pdftotext '.escapeshellarg($sourcePath).' - 2>/dev/null');
        if (! $text || strlen(trim($text)) < 20) {
            return null;
        }

        return FlassaLicenceTextParser::parse($text);
    }

    /**
     * The card's issuance date, read from the PDF's own CreationDate — only
     * trusted for FLASSA (see PdfMetadata), and only meaningful for a PDF
     * upload, not a photographed scan.
     */
    private function extractCardIssuedAt(string $sourcePath, ?string $federationAcronym): ?Carbon
    {
        if ($federationAcronym !== 'FLASSA' || ! str_contains(mime_content_type($sourcePath) ?: '', 'pdf')) {
            return null;
        }

        return PdfMetadata::creationDate($sourcePath);
    }

    /** Renders the file's first page to a modest-resolution PNG; returns the temp path, or null on failure. */
    private function renderFirstPage(string $sourcePath): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'licence').'.png';
        $base = str_replace('.png', '', $tmp);

        if (str_contains(mime_content_type($sourcePath) ?: '', 'pdf')) {
            exec('pdftoppm -png -r 150 -singlefile '.escapeshellarg($sourcePath).' '.escapeshellarg($base).' 2>/dev/null');

            return file_exists($tmp) ? $tmp : null;
        }

        // Already an image (jpg/png upload) — just downscale, no external
        // binary needed (Intervention Image is already a dependency).
        try {
            Image::decode(file_get_contents($sourcePath))->scaleDown(1200, 1200)->save($tmp);

            return $tmp;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Only ever returns a member when the extracted name unambiguously names exactly one of them. */
    private function findExactMatch(?string $name): ?User
    {
        if (! $name) {
            return null;
        }

        $needle = $this->normalize($name);
        if ($needle === []) {
            return null;
        }

        $matches = User::whereHas('detail')->with('detail')->get()->filter(function (User $user) use ($needle): bool {
            $haystack = $this->normalize(trim(($user->detail->first_name ?? '').' '.($user->detail->last_name ?? '')));

            return $haystack !== [] && $needle === $haystack;
        });

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** @return list<string> Sorted, accent-stripped, uppercased name tokens. */
    private function normalize(string $name): array
    {
        $tokens = array_values(array_filter(explode(' ', Str::of($name)->ascii()->upper()->squish()->toString())));
        sort($tokens);

        return $tokens;
    }
}
