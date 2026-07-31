<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * OCR a medical certificate to extract the establishment date.
 * Runs Tesseract on the uploaded file, finds date patterns, and
 * suggests the most likely one as date_established (if not already set).
 */
class OcrMedicalCert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public int $documentId) {}

    public function handle(): void
    {
        $doc = Document::find($this->documentId);
        if (! $doc || $doc->category !== 'medical' || $doc->date_established) {
            return; // already has a date or not a medical cert
        }

        $path = Storage::disk('local')->path($doc->file_path);
        if (! file_exists($path)) {
            $path = storage_path('app/public/'.$doc->file_path);
        }
        if (! file_exists($path)) {
            return;
        }

        $text = $this->extractText($path, $doc->mime_type);
        if (! $text) {
            return;
        }

        $date = $this->findDate($text);
        if ($date) {
            $doc->update([
                'date_established' => $date,
                'compliance_notes' => trim($doc->compliance_notes.' | OCR date detected: '.$date),
            ]);
            Log::info("OCR detected date {$date} for document #{$doc->id}");
        }
    }

    private function extractText(string $path, ?string $mime): ?string
    {
        if (str_contains($mime ?? '', 'pdf')) {
            // Try pdftotext first (fast, works for text PDFs)
            $text = shell_exec('pdftotext '.escapeshellarg($path).' - 2>/dev/null');
            if ($text && strlen(trim($text)) > 20) {
                return $text;
            }
            // Convert PDF to image then OCR
            $tmpImg = tempnam(sys_get_temp_dir(), 'ocr').'.png';
            exec('pdftoppm -png -singlefile '.escapeshellarg($path).' '.escapeshellarg(str_replace('.png', '', $tmpImg)).' 2>/dev/null');
            if (file_exists($tmpImg)) {
                $text = shell_exec('tesseract '.escapeshellarg($tmpImg).' stdout -l fra+eng 2>/dev/null');
                @unlink($tmpImg);

                return $text;
            }

            return null;
        }

        // Image — direct OCR
        return shell_exec('tesseract '.escapeshellarg($path).' stdout -l fra+eng 2>/dev/null');
    }

    /** Find the most likely medical cert date in OCR text. */
    private function findDate(string $text): ?string
    {
        $dates = [];

        // DD/MM/YYYY or DD.MM.YYYY or DD-MM-YYYY
        if (preg_match_all('/(\d{1,2})[\/.\-](\d{1,2})[\/.\-](20\d{2})/', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $d = (int) $m[1];
                $mo = (int) $m[2];
                $y = (int) $m[3];
                if ($mo >= 1 && $mo <= 12 && $d >= 1 && $d <= 31 && $y >= 2020 && $y <= 2030) {
                    $dates[] = sprintf('%04d-%02d-%02d', $y, $mo, $d);
                }
            }
        }

        // YYYY-MM-DD (ISO)
        if (preg_match_all('/(20\d{2})-(\d{2})-(\d{2})/', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $dates[] = $m[0];
            }
        }

        if ($dates === []) {
            return null;
        }

        // Pick the most recent date that's not in the future
        $today = date('Y-m-d');
        $valid = array_filter($dates, fn ($d): bool => $d <= $today);

        return $valid ? max($valid) : null;
    }
}
