<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BankTransaction;
use App\Models\ExternalRegistration;
use App\Models\PaymentExpected;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
     * First pass: rule-based match on communication text, amount, name, and
     * IBAN (see matchScore()) — a communication that contains a payment's
     * exact reference already scores above the threshold alone, so this
     * covers the "exact line" cases without needing AI.
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
     * Second pass, for whatever suggestMatches() couldn't resolve on rules
     * alone (typos, abbreviated or missing references, generic transfer
     * text) — sends both remaining lists to Cloudflare Workers AI in one
     * call and asks it to propose matches. Every result still lands as
     * status 'suggested', exactly like the rule-based pass: nothing here
     * ever marks a payment paid on its own, a bureau member always
     * confirms via the existing review screen.
     */
    public function aiMatchRemaining(): array
    {
        $unmatched = BankTransaction::where('status', 'unmatched')->get();
        $pending = PaymentExpected::whereIn('status', ['pending', 'partial'])->with('user.detail')->get();

        if ($unmatched->isEmpty() || $pending->isEmpty()) {
            return [];
        }

        $proposals = $this->askCloudflareToMatch($unmatched, $pending);
        if ($proposals === null) {
            return [];
        }

        $matches = [];
        $usedPaymentIds = [];

        foreach ($proposals as $proposal) {
            $confidence = (int) ($proposal['confidence'] ?? 0);
            $tx = $unmatched->firstWhere('id', $proposal['transaction_id'] ?? null);
            $pe = $pending->firstWhere('id', $proposal['payment_id'] ?? null);

            // Never trust the model's ids blindly, and never let it double-book
            // one payment to two transactions in the same batch.
            if (! $tx || ! $pe || $confidence < 40 || in_array($pe->id, $usedPaymentIds, true)) {
                continue;
            }

            $reason = is_string($proposal['reason'] ?? null) ? mb_substr($proposal['reason'], 0, 500) : null;
            $tx->update(['matched_payment_id' => $pe->id, 'match_score' => $confidence, 'match_reason' => $reason, 'status' => 'suggested']);
            $usedPaymentIds[] = $pe->id;
            $matches[] = ['transaction' => $tx, 'payment' => $pe, 'score' => $confidence, 'reason' => $reason];
        }

        return $matches;
    }

    /**
     * @param  Collection<int, BankTransaction>  $transactions
     * @param  Collection<int, PaymentExpected>  $payments
     * @return list<array{transaction_id?: mixed, payment_id?: mixed, confidence?: mixed, reason?: mixed}>|null
     */
    private function askCloudflareToMatch(Collection $transactions, Collection $payments): ?array
    {
        $accountId = config('services.cloudflare.account_id');
        $apiToken = config('services.cloudflare.api_token');
        if (! $accountId || ! $apiToken) {
            Log::warning('Cloudflare Workers AI credentials not configured for bank matching');

            return null;
        }

        $txPayload = $transactions->map(fn (BankTransaction $t): array => [
            'transaction_id' => $t->id,
            'amount' => (float) $t->amount,
            'communication' => $t->communication,
            'counterparty' => $t->counterparty,
            'date' => $t->transaction_date?->format('Y-m-d'),
        ])->values()->all();

        $paymentPayload = $payments->map(fn (PaymentExpected $p): array => [
            'payment_id' => $p->id,
            'amount_outstanding' => round((float) $p->amount_due - (float) $p->amount_paid, 2),
            'communication' => $p->communication,
            'member_name' => $p->user?->detail ? trim(($p->user->detail->first_name ?? '').' '.($p->user->detail->last_name ?? '')) : null,
        ])->values()->all();

        $prompt = 'You are matching bank transactions to expected club membership payments for a diving club.'
            .' Each transaction matches at most one payment, and each payment at most one transaction.'
            .' Consider the communication/reference text (allow for typos, abbreviations, missing accents or a partially-entered reference),'
            .' the amount (should be close — allow small differences for bank fees), and the counterparty name versus the member name.'
            ."\n\nExpected payments:\n".json_encode($paymentPayload)
            ."\n\nReceived transactions:\n".json_encode($txPayload)
            ."\n\nRespond with ONLY a JSON array, no other text, in exactly this shape:\n"
            .'[{"transaction_id": <int>, "payment_id": <int>, "confidence": <integer 0-100>, "reason": "<short reason, one sentence>"}]'
            .' Omit any transaction you cannot plausibly match to a payment. Never invent an id that is not in the input above.';

        try {
            $response = Http::withToken($apiToken)->timeout(30)->post(
                "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/@cf/meta/llama-3.1-8b-instruct",
                ['messages' => [['role' => 'user', 'content' => $prompt]]]
            );

            if (! $response->ok()) {
                Log::warning('Cloudflare AI bank-match request failed', ['status' => $response->status(), 'body' => $response->body()]);

                return null;
            }

            $content = $response->json('result.choices.0.message.content');
            if (! is_string($content) || $content === '') {
                return null;
            }

            // The model occasionally wraps its JSON in a code fence despite
            // being told not to — strip that before decoding.
            $content = trim((string) preg_replace('/^```(?:json)?|```$/m', '', trim($content)));
            $decoded = json_decode($content, true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            Log::warning('Cloudflare AI bank-match failed', ['error' => $e->getMessage()]);

            return null;
        }
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
