<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BankTransaction;
use App\Models\ExternalRegistration;
use App\Models\PaymentExpected;

class BankReconciliationService
{
    /**
     * Parse pasted bank statement text into transactions.
     * Expected format: one line per transaction, tab or semicolon separated:
     * date;amount;communication;counterparty
     */
    public function parseStatement(string $text): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $text)));
        $transactions = [];

        foreach ($lines as $line) {
            $parts = preg_split('/[;\t]/', $line);
            if (count($parts) < 2) {
                continue;
            }

            $transactions[] = BankTransaction::create([
                'transaction_date' => $this->parseDate($parts[0] ?? ''),
                'amount' => (float) str_replace(',', '.', $parts[1] ?? '0'),
                'communication' => trim($parts[2] ?? ''),
                'counterparty' => trim($parts[3] ?? ''),
            ]);
        }

        return $transactions;
    }

    /**
     * Parse a PDF bank statement via OCR, then extract transactions.
     *
     * @param  string  $pdfPath  Absolute path to the uploaded PDF
     * @param  string|null  $statementRef  Optional statement number/reference
     * @return array{transactions: BankTransaction[], raw_text: string, page_count: int}
     */
    public function parsePdfStatement(string $pdfPath, ?string $statementRef = null): array
    {
        $text = $this->ocrPdf($pdfPath);
        $pageCount = substr_count($text, "\f") + 1;

        $transactions = $this->parseStatement($text);

        // Tag transactions with statement reference
        if ($statementRef) {
            foreach ($transactions as $tx) {
                $tx->update(['statement_ref' => $statementRef]);
            }
        }

        return ['transactions' => $transactions, 'raw_text' => $text, 'page_count' => $pageCount];
    }

    /**
     * Run OCR on a PDF file. Tries Tesseract (local) first, falls back to pdftotext.
     */
    private function ocrPdf(string $pdfPath): string
    {
        // Strategy 1: pdftotext (works for digital/text-based PDFs)
        $textPath = tempnam(sys_get_temp_dir(), 'bank_').'.txt';
        $escaped = escapeshellarg($pdfPath);
        $escapedOut = escapeshellarg($textPath);
        exec("pdftotext -layout {$escaped} {$escapedOut} 2>/dev/null", $output, $code);

        if ($code === 0 && file_exists($textPath)) {
            $text = file_get_contents($textPath);
            @unlink($textPath);

            // If pdftotext returned meaningful content, use it
            if (strlen(trim($text)) > 50) {
                return $text;
            }
        }
        @unlink($textPath);

        // Strategy 2: Tesseract OCR (for scanned PDFs)
        $imgDir = tempnam(sys_get_temp_dir(), 'bank_img_');
        @unlink($imgDir);
        @mkdir($imgDir);

        try {
            // Convert PDF pages to images
            exec("pdftoppm -png -r 300 {$escaped} {$imgDir}/page 2>/dev/null", $output, $code);

            if ($code !== 0) {
                return '';
            }

            $pages = glob("{$imgDir}/page-*.png");
            sort($pages);
            $fullText = '';

            foreach ($pages as $page) {
                $escapedPage = escapeshellarg($page);
                $ocrResult = '';
                exec("tesseract {$escapedPage} stdout -l fra+deu+eng 2>/dev/null", $ocrLines, $ocrCode);
                if ($ocrCode === 0) {
                    $fullText .= implode("\n", $ocrLines)."\f";
                }
                $ocrLines = [];
            }

            return $fullText;
        } finally {
            @array_map('unlink', glob("{$imgDir}/*"));
            @rmdir($imgDir);
        }
    }

    /**
     * Auto-match unmatched transactions against pending payments using fuzzy communication match.
     */
    public function suggestMatches(): array
    {
        $unmatched = BankTransaction::where('status', 'unmatched')->get();
        $pending = PaymentExpected::whereIn('status', ['pending', 'partial'])->with('user.detail')->get();
        $matches = [];

        foreach ($unmatched as $tx) {
            $bestMatch = null;
            $bestScore = 0;

            foreach ($pending as $pe) {
                $score = $this->matchScore($tx, $pe);
                if ($score > $bestScore && $score >= 60) {
                    $bestScore = $score;
                    $bestMatch = $pe;
                }
            }

            if ($bestMatch) {
                $tx->update(['matched_payment_id' => $bestMatch->id, 'match_score' => $bestScore, 'status' => 'suggested']);
                $matches[] = ['transaction' => $tx, 'payment' => $bestMatch, 'score' => $bestScore];
            }
        }

        return $matches;
    }

    /**
     * Confirm a match — mark payment as paid.
     */
    public function confirmMatch(BankTransaction $tx): void
    {
        $payment = $tx->matchedPayment;
        if (! $payment) {
            return;
        }

        $payment->update([
            'amount_paid' => $payment->amount_paid + $tx->amount,
            'status' => ($payment->amount_paid + $tx->amount) >= $payment->amount_due ? 'paid' : 'partial',
            'paid_at' => $tx->transaction_date,
            'reconciled_by' => auth()->user()?->name,
            'reconciled_at' => now(),
            'bank_statement_ref' => $tx->statement_ref ?? $tx->transaction_ref,
            'bank_statement_date' => $tx->transaction_date,
        ]);

        $tx->update(['status' => 'confirmed', 'confirmed_by' => auth()->id()]);
    }

    private function matchScore(BankTransaction $tx, PaymentExpected $pe): int
    {
        $score = 0;

        // Exact communication match
        if ($pe->communication && stripos($tx->communication, $pe->communication) !== false) {
            $score += 80;
        }

        // Amount match
        if (abs($tx->amount - $pe->amount_due) < 0.01) {
            $score += 20;
        } elseif (abs($tx->amount - $pe->amount_due) < 5.00) {
            $score += 10;
        }

        // Name in communication
        $name = $pe->user?->detail?->last_name;
        if (! $name && $pe->event_id) {
            $name = ExternalRegistration::where('event_id', $pe->event_id)
                ->value('external_member_name');
        }
        if ($name && stripos($tx->communication, $name) !== false) {
            $score += 30;
        }

        // IBAN match: counterparty IBAN matches member's stored IBAN
        $iban = $pe->user?->detail?->iban;
        if (! $iban && $pe->event_id) {
            // Check external registrations for this event
            $iban = ExternalRegistration::where('event_id', $pe->event_id)
                ->whereNotNull('external_member_iban')
                ->pluck('external_member_iban')
                ->first(fn (string $i): bool => $tx->counterparty && $this->normalizeIban($i) === $this->normalizeIban($tx->counterparty));
        }
        if ($iban && $tx->counterparty && $this->normalizeIban($iban) === $this->normalizeIban($tx->counterparty)) {
            $score += 50;
        }

        return min($score, 100);
    }

    private function normalizeIban(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban));
    }

    private function parseDate(string $d): string
    {
        // Try dd/mm/yyyy, dd-mm-yyyy, yyyy-mm-dd
        if (preg_match('#(\d{2})[/\-](\d{2})[/\-](\d{4})#', $d, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return $d ?: now()->format('Y-m-d');
    }
}
