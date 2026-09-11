<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\LicenceScan;
use App\Models\MemberLicence;
use App\Models\User;
use App\Services\CloudflareVisionOcrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Renders a licence scan's first page to a lightweight image, reads the
 * holder name / licence number / year off it via Cloudflare Workers AI, and
 * either applies it straight to the one exactly-matching member or leaves it
 * for a bureau member to assign by hand.
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

        $fields = $ocr->extractLicenceFields(Storage::disk('local')->path($imageDisk));
        if (! $fields) {
            $scan->update(['status' => 'needs_review', 'extraction_error' => 'Automatic field extraction failed — enter the fields manually.']);

            return;
        }

        $scan->update([
            'extracted_name' => $fields['name'],
            'extracted_number' => $fields['licence_number'],
            'extracted_year' => $fields['year'],
        ]);

        $match = $this->findExactMatch($fields['name']);
        if (! $match) {
            $scan->update(['status' => 'needs_review']);

            return;
        }

        MemberLicence::updateOrCreate(
            ['user_id' => $match->id, 'federation_id' => $scan->federation_id],
            ['licence_number' => $fields['licence_number'], 'season' => $fields['year'], 'scan_image_path' => $imageDisk],
        );

        $scan->update(['status' => 'applied', 'matched_user_id' => $match->id]);

        Log::info("Licence scan #{$scan->id} auto-applied to user #{$match->id}");
    }

    /** Renders the file's first page to a modest-resolution PNG; returns the temp path, or null on failure. */
    private function renderFirstPage(string $sourcePath): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'licence').'.png';
        $base = str_replace('.png', '', $tmp);

        if (str_contains(mime_content_type($sourcePath) ?: '', 'pdf')) {
            exec('pdftoppm -png -r 150 -singlefile '.escapeshellarg($sourcePath).' '.escapeshellarg($base).' 2>/dev/null');
        } else {
            exec('convert '.escapeshellarg($sourcePath).' -resize 1200x1200\> '.escapeshellarg($tmp).' 2>/dev/null');
        }

        return file_exists($tmp) ? $tmp : null;
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
        $tokens = array_filter(explode(' ', Str::of($name)->ascii()->upper()->squish()->toString()));
        sort($tokens);

        return array_values($tokens);
    }
}
