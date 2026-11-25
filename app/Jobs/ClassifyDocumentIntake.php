<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DocumentIntake;
use App\Models\LicenceScan;
use App\Services\BankReconciliationService;
use App\Services\DocumentClassifierService;
use App\Services\FfessmBrevetImportService;
use App\Services\PdfTextExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Classifies one document dropped on the shared intake screen and routes it
 * to whichever existing pipeline handles that type — DocumentIntake is just
 * the record of what was assumed and what happened; LicenceScan and
 * BankTransaction (via BankReconciliationService) keep doing the real work,
 * completely unchanged.
 */
class ClassifyDocumentIntake implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 90;

    /**
     * $forcedType/$forcedFederationId are set when a bureau member manually
     * reclassified a "needs_review" intake — skips auto-classification
     * entirely and routes straight to the chosen pipeline.
     */
    public function __construct(
        public int $intakeId,
        public ?string $forcedType = null,
        public ?int $forcedFederationId = null,
    ) {}

    public function handle(PdfTextExtractionService $extractor, DocumentClassifierService $classifier, BankReconciliationService $bankSvc): void
    {
        $intake = DocumentIntake::find($this->intakeId);
        if (! $intake) {
            return;
        }

        $sourcePath = Storage::disk('local')->path($intake->file_path);
        if (! file_exists($sourcePath)) {
            $intake->update(['status' => 'failed', 'error' => 'Uploaded file is missing on disk.']);

            return;
        }

        if ($this->forcedType === 'licence_scan') {
            $intake->update(['detected_type' => 'licence_scan', 'detection_reason' => 'Set manually.', 'federation_id' => $this->forcedFederationId]);
            $this->routeToLicenceScan($intake, $this->forcedFederationId);

            return;
        }
        if ($this->forcedType === 'bank_statement') {
            $intake->update(['detected_type' => 'bank_statement', 'detection_reason' => 'Set manually.', 'federation_id' => null]);
            if ($this->isXlsx($intake->original_filename)) {
                $this->routeToXlsxBankStatement($intake, $bankSvc, $sourcePath);
            } else {
                $this->routeToBankStatement($intake, $bankSvc, $extractor->extract($sourcePath));
            }

            return;
        }
        if ($this->forcedType === 'ffessm_brevet') {
            $intake->update(['detected_type' => 'ffessm_brevet', 'detection_reason' => 'Set manually.']);
            $this->routeToFfessmBrevet($intake, $sourcePath);

            return;
        }

        $mime = mime_content_type($sourcePath) ?: '';
        if (! str_contains($mime, 'pdf')) {
            // A bank statement is always exported as a PDF — a photographed
            // image can only sensibly be a licence card. The federation
            // still needs a human pick, since there's no text to classify
            // from without running OCR on it first.
            $intake->update(['detected_type' => 'licence_scan', 'detection_reason' => 'Image upload — only a licence scan can be a photo.', 'status' => 'needs_review']);

            return;
        }

        $text = $extractor->extract($sourcePath);
        if (trim($text) === '') {
            $intake->update(['status' => 'needs_review', 'error' => 'Could not read any text from this file — pick the type manually.']);

            return;
        }

        $result = $classifier->classify($text);
        $intake->update(['detected_type' => $result['type'] ?: null, 'detection_reason' => $result['reason'], 'federation_id' => $result['federation_id']]);

        match ($result['type']) {
            'bank_statement' => $this->routeToBankStatement($intake, $bankSvc, $text),
            'licence_scan' => $this->routeToLicenceScan($intake, $result['federation_id']),
            default => $intake->update(['status' => 'needs_review']),
        };
    }

    private function routeToBankStatement(DocumentIntake $intake, BankReconciliationService $bankSvc, string $text): void
    {
        $transactions = $bankSvc->parseStatement($text);
        foreach ($transactions as $tx) {
            $tx->update(['statement_ref' => $intake->original_filename]);
        }

        if ($transactions === []) {
            $intake->update(['status' => 'needs_review', 'error' => 'No transactions could be extracted from this statement.', 'transactions_created' => 0]);

            return;
        }

        $intake->update(['status' => 'routed', 'transactions_created' => count($transactions)]);
    }

    private function isXlsx(string $originalFilename): bool
    {
        return str_ends_with(strtolower($originalFilename), '.xlsx');
    }

    /**
     * The bank's XLSX account-history export — parsed directly by
     * BankReconciliationService::parseXlsxStatement(), which already stamps
     * $intake->original_filename as each transaction's statement_ref and
     * dedups against previously imported lines, same as the PDF path below.
     */
    private function routeToXlsxBankStatement(DocumentIntake $intake, BankReconciliationService $bankSvc, string $sourcePath): void
    {
        try {
            $result = $bankSvc->parseXlsxStatement($sourcePath, $intake->original_filename);
        } catch (\RuntimeException $e) {
            $intake->update(['status' => 'failed', 'error' => $e->getMessage()]);

            return;
        }

        $count = count($result['transactions']);
        if ($count === 0) {
            $intake->update(['status' => 'needs_review', 'error' => 'No transactions could be extracted from this statement.', 'transactions_created' => 0]);

            return;
        }

        $intake->update(['status' => 'routed', 'transactions_created' => $count]);
    }

    private function routeToFfessmBrevet(DocumentIntake $intake, string $sourcePath): void
    {
        try {
            $result = app(FfessmBrevetImportService::class)->import($sourcePath, $intake->original_filename);
        } catch (\RuntimeException $e) {
            $intake->update(['status' => 'failed', 'error' => $e->getMessage()]);

            return;
        }

        $unresolved = [...$result['unmatched_names'], ...$result['ambiguous_names'], ...$result['unmatched_levels']];

        $intake->update([
            'import_result' => $result,
            'status' => $unresolved === [] ? 'routed' : 'needs_review',
            'error' => $unresolved === []
                ? null
                : __('Not applied — needs a manual look: :count name(s)/level(s) (:names).', [
                    'count' => count($unresolved), 'names' => implode(', ', $unresolved),
                ]),
        ]);
    }

    private function routeToLicenceScan(DocumentIntake $intake, ?int $federationId): void
    {
        if (! $federationId) {
            $intake->update(['status' => 'needs_review', 'error' => 'Could not tell which federation this licence is for — pick it manually.']);

            return;
        }

        $scan = LicenceScan::create([
            'federation_id' => $federationId,
            'original_filename' => $intake->original_filename,
            'file_path' => $intake->file_path,
            'status' => 'processing',
            'uploaded_by' => $intake->uploaded_by,
        ]);

        $intake->update(['status' => 'routed', 'licence_scan_id' => $scan->id]);

        ProcessLicenceScan::dispatch($scan->id)->afterCommit();
    }
}
