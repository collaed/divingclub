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
     * Parse bank statement text into transactions.
     *
     * The fast path handles text pasted in the simple format the admin UI
     * asks for: one line per transaction, tab or semicolon separated
     * (date;amount;communication;counterparty). Real PDF exports almost
     * never look like that — multi-line entries, address lines, labelled
     * sub-fields, comma-decimal amounts, a trailing +/- sign — and the
     * shape varies from bank to bank, so rather than chase every export
     * format with regex, fall back to asking Cloudflare Workers AI to read
     * the whole text and hand back the same structured shape.
     */
    public function parseStatement(string $text): array
    {
        $transactions = $this->parseDelimitedLines($text);

        return $transactions !== [] ? $transactions : $this->aiParseStatement($text);
    }

    private function parseDelimitedLines(string $text): array
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
     * Only ever creates rows for incoming transfers — reconciliation only
     * ever matches against money the club is expecting, so an outgoing
     * standing order or fee has no business becoming a BankTransaction
     * that could later get matched to a due.
     */
    private function aiParseStatement(string $text): array
    {
        $rows = $this->askCloudflareToExtract($text);
        if ($rows === null) {
            return [];
        }

        $transactions = [];
        foreach ($rows as $row) {
            // The model is asked for YYYY-MM-DD but tends to echo the
            // source statement's own date format instead — normalize
            // through the same parser the simple pasted format uses, and
            // only trust the result once it is unambiguously Y-m-d.
            $normalizedDate = is_string($row['date'] ?? null) ? $this->parseDate($row['date']) : '';
            $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalizedDate) ? $normalizedDate : null;
            $amount = is_numeric($row['amount'] ?? null) ? (float) $row['amount'] : null;
            if (! $date || $amount === null || $amount <= 0) {
                continue;
            }

            $transactions[] = BankTransaction::create([
                'transaction_date' => $date,
                'amount' => $amount,
                'communication' => is_string($row['communication'] ?? null) ? mb_substr($row['communication'], 0, 500) : '',
                'counterparty' => is_string($row['counterparty'] ?? null) ? mb_substr($row['counterparty'], 0, 255) : '',
            ]);
        }

        return $transactions;
    }

    /** @return list<array{date?: mixed, amount?: mixed, communication?: mixed, counterparty?: mixed}>|null */
    private function askCloudflareToExtract(string $text): ?array
    {
        $accountId = config('services.cloudflare.account_id');
        $apiToken = config('services.cloudflare.api_token');
        if (! $accountId || ! $apiToken) {
            Log::warning('Cloudflare Workers AI credentials not configured for statement extraction');

            return null;
        }

        $prompt = 'This is raw text extracted from a bank account statement. The export format varies by bank:'
            .' entries may span several lines, mix in address lines, use labelled sub-fields ("Communication :", "Banque du donneur d\'ordre :"),'
            .' use a comma as the decimal separator, and mark the amount with a trailing + (credit) or - (debit) sign.'
            ."\n\nExtract ONLY the incoming transfers (credits — money received by the account holder). Ignore outgoing transfers, standing orders (\"ordre permanent\"), direct debits, and fees — anything marked as a debit or with a trailing minus sign."
            ."\n\nStatement text:\n".mb_substr($text, 0, 6000)
            ."\n\nRespond with ONLY a JSON array, no other text, in exactly this shape:\n"
            .'[{"date": "<YYYY-MM-DD>", "amount": <number, dot as decimal separator>, "communication": "<the reference/communication text for this transfer>", "counterparty": "<name of the person or organisation who sent the money>"}]'
            .' Omit any line that is not a clear incoming transfer.';

        try {
            $response = Http::withToken($apiToken)->timeout(30)->post(
                "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/@cf/meta/llama-3.1-8b-instruct",
                ['messages' => [['role' => 'user', 'content' => $prompt]]]
            );

            if (! $response->ok()) {
                Log::warning('Cloudflare AI statement extraction failed', ['status' => $response->status(), 'body' => $response->body()]);

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
            Log::warning('Cloudflare AI statement extraction failed', ['error' => $e->getMessage()]);

            return null;
        }
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
     * Run OCR on a PDF file. Tries pdftotext first, falls back to Tesseract.
     * Delegates to PdfTextExtractionService, shared with the document-intake
     * classifier so a statement's text is only ever extracted once.
     */
    private function ocrPdf(string $pdfPath): string
    {
        return app(PdfTextExtractionService::class)->extract($pdfPath);
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
        // Try dd/mm/yyyy, dd-mm-yyyy, dd.mm.yyyy, yyyy-mm-dd
        if (preg_match('#(\d{2})[./\-](\d{2})[./\-](\d{4})#', $d, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        // dd.mm.yy / dd/mm/yy / dd-mm-yy — some bank exports use a two-digit
        // year, and the AI extraction fallback tends to echo the source
        // format back despite being asked for YYYY-MM-DD. Guarded on both
        // sides so this can't match a substring of an already-valid
        // 4-digit-year date (e.g. the "26-07-08" tail of "2026-07-08").
        if (preg_match('#(?<!\d)(\d{2})[./\-](\d{2})[./\-](\d{2})(?!\d)#', $d, $m)) {
            $year = (int) $m[3] < 70 ? 2000 + (int) $m[3] : 1900 + (int) $m[3];

            return "{$year}-{$m[2]}-{$m[1]}";
        }

        return $d ?: now()->format('Y-m-d');
    }
}
