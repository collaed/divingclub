<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BankTransaction;
use App\Models\ExternalRegistration;
use App\Models\LedgerCounterparty;
use App\Models\MemberStatus;
use App\Models\PaymentExpected;
use App\Models\Season;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class BankReconciliationService
{
    /**
     * Same 15-column bank export LedgerStatementImportService expects.
     * An unrecognised header aborts the import rather than guessing
     * column positions.
     */
    private const EXPECTED_HEADER = [
        'Date transaction', 'Description', 'Date valeur', 'Montant en EUR', 'Extrait', 'Solde journalier',
        'Opération', 'Communication 1', 'Communication 2', 'Communication 3', 'Communication 4',
        'Compte bénéficiaire', 'Nom de la contrepartie', 'Adresse de la contrepartie', 'Localité de la contrepartie',
    ];

    /**
     * Parse the bank's own XLSX account-history export — chosen over PDF
     * for the same reason LedgerStatementImportService chose it over CSV:
     * typed dates/amounts and clean Unicode, no OCR or layout guessing.
     * The PDF export only carries date/description/amount/balance; this
     * format also gives a real IBAN (`Compte bénéficiaire`) for the
     * existing IBAN sub-score in matchScore(), and the four communication
     * lines plus counterparty name for the text/name sub-scores.
     *
     * @return array{transactions: BankTransaction[], page_count: int, duplicates: int}
     */
    public function parseXlsxStatement(string $path, ?string $statementRef = null): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        // formatData=false: same reasoning as LedgerStatementImportService —
        // raw Excel serial dates, converted explicitly below.
        $rows = $sheet->toArray(null, true, false, false);

        if ($rows === [] || array_slice($rows[0], 0, 15) !== self::EXPECTED_HEADER) {
            throw new \RuntimeException('Unrecognised statement format: the column headers don\'t match the expected export.');
        }

        $transactions = [];
        $duplicates = 0;
        foreach (array_slice($rows, 1) as $row) {
            if (($row[0] ?? null) === null || $row[0] === '') {
                continue; // blank separator row
            }

            $amount = round((float) ($row[3] ?? 0), 2);
            if ($amount <= 0) {
                continue; // reconciliation only ever matches incoming transfers
            }

            $communication = trim(implode(' ', array_filter([
                $row[7] ?? null, $row[8] ?? null, $row[9] ?? null, $row[10] ?? null, $row[12] ?? null,
            ], fn ($v) => $v !== null && $v !== '')));
            // "Compte bénéficiaire" (IBAN) is blank on most of this bank's
            // own lines in practice — when it is, fall back to "Nom de la
            // contrepartie" so there's still something for learnCounterparty()
            // to remember (it already handles either an IBAN or a plain
            // name here, see matchScore()'s "Known account" leg) instead of
            // silently discarding a real counterparty name.
            $iban = trim((string) ($row[11] ?? ''));
            $counterparty = $iban !== '' ? $iban : trim((string) ($row[12] ?? ''));
            $date = $this->asDate($row[0]);

            // The bank's export is always the *full* account history, not a
            // diff — re-uploading it (e.g. to pick up the latest few lines)
            // re-presents every previously-seen line too. Without this check
            // every one of those got re-inserted as a fresh, unmatched
            // duplicate on each re-import (see devdocs/payments.md).
            // Checked against both the new value ($counterparty, what every
            // row imported from now on will have) and the blank IBAN alone
            // (what every row imported before this fix has stored) — a line
            // already on file under either scheme must still be recognised.
            $alreadyImported = $this->alreadyImported($date, $amount, $communication, $counterparty)
                || ($iban !== $counterparty && $this->alreadyImported($date, $amount, $communication, $iban));
            if ($alreadyImported) {
                $duplicates++;

                continue;
            }

            $transactions[] = BankTransaction::create([
                'transaction_date' => $date,
                'amount' => $amount,
                'communication' => $communication,
                'counterparty' => $counterparty,
                'statement_ref' => $statementRef,
            ]);
        }

        return ['transactions' => $transactions, 'page_count' => 1, 'duplicates' => $duplicates];
    }

    /** Same identity check used by every import path below — see parseXlsxStatement(). */
    private function alreadyImported(Carbon|string|null $date, float $amount, string $communication, string $counterparty): bool
    {
        return BankTransaction::where('transaction_date', $date)->where('amount', $amount)
            ->where('communication', $communication)->where('counterparty', $counterparty)->exists();
    }

    /** @param  mixed  $value  the raw cell value: an Excel date serial number, or empty */
    private function asDate(mixed $value): ?Carbon
    {
        if (! is_numeric($value)) {
            return null;
        }

        return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
    }

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

            $date = $this->parseDate($parts[0] ?? '');
            $amount = (float) str_replace(',', '.', $parts[1] ?? '0');
            $communication = trim($parts[2] ?? '');
            $counterparty = trim($parts[3] ?? '');
            if ($this->alreadyImported($date, $amount, $communication, $counterparty)) {
                continue;
            }

            $transactions[] = BankTransaction::create([
                'transaction_date' => $date,
                'amount' => $amount,
                'communication' => $communication,
                'counterparty' => $counterparty,
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

            $communication = is_string($row['communication'] ?? null) ? mb_substr($row['communication'], 0, 500) : '';
            $counterparty = is_string($row['counterparty'] ?? null) ? mb_substr($row['counterparty'], 0, 255) : '';
            if ($this->alreadyImported($date, $amount, $communication, $counterparty)) {
                continue;
            }

            $transactions[] = BankTransaction::create([
                'transaction_date' => $date,
                'amount' => $amount,
                'communication' => $communication,
                'counterparty' => $counterparty,
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
            $content = trim((string) preg_replace('/(?:^```(?:json)?)|(?:```$)/m', '', trim($content)));
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
                Log::info('Bank reconciliation match suggested (rule-based)', [
                    'bank_transaction_id' => $tx->id,
                    'amount' => $tx->amount,
                    'communication' => $tx->communication,
                    'payment_expected_id' => $bestMatch->id,
                    'user_id' => $bestMatch->user_id,
                    'amount_due' => $bestMatch->amount_due,
                    'score' => $bestScore,
                ]);
            } else {
                Log::info('Bank reconciliation: no rule-based match', ['bank_transaction_id' => $tx->id, 'amount' => $tx->amount, 'communication' => $tx->communication]);
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
            Log::info('Bank reconciliation match suggested (AI)', [
                'bank_transaction_id' => $tx->id,
                'amount' => $tx->amount,
                'communication' => $tx->communication,
                'payment_expected_id' => $pe->id,
                'user_id' => $pe->user_id,
                'confidence' => $confidence,
                'reason' => $reason,
            ]);
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
            $content = trim((string) preg_replace('/(?:^```(?:json)?)|(?:```$)/m', '', trim($content)));
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

        app(MembershipRenewalService::class)->recordSeasonPaid($payment->fresh());

        $tx->update(['status' => 'confirmed', 'confirmed_by' => auth()->id()]);

        $this->learnCounterparty($tx, $payment->user);

        Log::info('Bank reconciliation match confirmed', [
            'bank_transaction_id' => $tx->id,
            'payment_expected_id' => $payment->id,
            'user_id' => $payment->user_id,
            'amount' => $tx->amount,
            'new_status' => $payment->status,
            'amount_paid' => $payment->amount_paid,
            'amount_due' => $payment->amount_due,
        ]);
    }

    /**
     * For a transaction that matches no PaymentExpected at all — the member
     * paid without ever clicking "I commit" on the renewal flow. Derives
     * what they should actually owe from the real price list (never just
     * trusts the paid amount), so the bureau gets a one-click fix only when
     * that derivation matches what was actually paid.
     *
     * @return array{user: User, amount_due: float, components: array<string, mixed>, communication: string, clean_match: bool, matched_by: string}|null
     */
    public function proposeBackfill(BankTransaction $tx): ?array
    {
        ['user' => $user, 'matchedBy' => $matchedBy] = $this->resolveReferencedOrNamedUser($tx);
        if (! $user) {
            return null;
        }

        return $this->buildBackfillProposal($tx, $user, (string) $matchedBy);
    }

    /** @return array{user: ?User, matchedBy: ?string} */
    private function resolveReferencedOrNamedUser(BankTransaction $tx): array
    {
        if (preg_match('/CEP-\d{4}-(\d+)-/i', (string) $tx->communication, $m) === 1 && (int) $m[1] > 0) {
            $user = User::find((int) $m[1]);
            if ($user) {
                return ['user' => $user, 'matchedBy' => 'reference'];
            }
        }

        $detail = app(LedgerClassificationService::class)->matchMember((string) $tx->communication);

        return ['user' => $detail?->user, 'matchedBy' => $detail?->user ? 'name' : null];
    }

    /**
     * Same derivation as proposeBackfill(), but for a member the bureau
     * identified by hand instead of one found by reference/name matching —
     * used when a transaction's counterparty is recognized visually but
     * matches no regex pattern and no PaymentExpected.
     *
     * @return array{user: User, amount_due: float, components: array<string, mixed>, communication: string, clean_match: bool, matched_by: string}
     */
    public function proposeBackfillForUser(BankTransaction $tx, User $user): array
    {
        return $this->buildBackfillProposal($tx, $user, 'manual');
    }

    /** @return array{user: User, amount_due: float, components: array<string, mixed>, communication: string, clean_match: bool, matched_by: string} */
    private function buildBackfillProposal(BankTransaction $tx, User $user, string $matchedBy): array
    {
        ['category' => $category, 'insurance' => $insurance] = app(LedgerClassificationService::class)->parseCotisationText((string) $tx->communication);
        $statusOverride = $category ? MemberStatus::where('slug', $category)->first() : null;
        $optionalSlugs = ($insurance && $insurance !== 'none') ? ["ass_{$insurance}"] : [];

        $calc = app(FeeCalculationService::class)->calculate($user, (string) Season::currentDuesYear(), $optionalSlugs, $statusOverride);

        return [
            'user' => $user,
            'amount_due' => $calc['amount_due'],
            'components' => $calc['components'],
            'communication' => $calc['communication'],
            'clean_match' => abs($calc['amount_due'] - (float) $tx->amount) < 0.005,
            'matched_by' => $matchedBy,
        ];
    }

    /**
     * Only ever called right after a fresh server-side proposeBackfill()
     * confirms clean_match — never trusts a client-submitted amount.
     *
     * @param  array{user: User, amount_due: float, components: array<string, mixed>, communication: string, clean_match: bool, matched_by: string}  $proposal
     */
    public function createAndConfirmBackfill(BankTransaction $tx, array $proposal): void
    {
        $payment = PaymentExpected::create([
            'user_id' => $proposal['user']->id,
            'type' => 'membership',
            'season_year' => Season::currentDuesYear(),
            'amount_due' => $proposal['amount_due'],
            'amount_paid' => 0,
            'status' => 'pending',
            'components' => $proposal['components'],
            'note' => "Backfilled from bank transaction #{$tx->id} — paid without a prior commit.",
        ]);

        $tx->update(['matched_payment_id' => $payment->id]);

        $this->confirmMatch($tx->refresh());

        Log::info('Bank reconciliation backfill created', [
            'bank_transaction_id' => $tx->id,
            'payment_expected_id' => $payment->id,
            'user_id' => $payment->user_id,
            'amount' => $tx->amount,
            'new_status' => $payment->status,
            'amount_paid' => $payment->amount_paid,
            'amount_due' => $payment->amount_due,
        ]);
    }

    /**
     * For a transaction that matches no single PaymentExpected and no clean
     * single-person backfill (proposeBackfill() returned null or a dirty
     * match) — tries a family lump sum: an anchor member resolved the same
     * way proposeBackfill() does, whose family_id groups several members,
     * each contributing their own share of the total. Every family member
     * is assumed to be part of the payment (the real-world case described:
     * "2 adults + 1 kid" summed) — this does not search partial subsets of
     * the family, which keeps the combinatorics small and matches how
     * these payments actually happen.
     *
     * Never auto-applies anything — always returns candidates for a bureau
     * member to review and click, even when there is only one candidate and
     * it scores the maximum confidence.
     *
     * @return array{members: Collection<int, User>, candidates: list<array{breakdown: list<array{user: User, label: string, amount: float}>, total: float, confidence: int}>}|null
     */
    public function proposeFamilyBackfill(BankTransaction $tx): ?array
    {
        ['user' => $anchor] = $this->resolveReferencedOrNamedUser($tx);
        $familyId = $anchor?->detail?->family_id;
        if ($familyId === null) {
            return null;
        }

        $members = User::whereHas('detail', fn ($q) => $q->where('family_id', $familyId))->with('detail')->get();
        if ($members->count() < 2) {
            return null;
        }

        $year = (string) Season::currentDuesYear();
        $insuranceHint = app(LedgerClassificationService::class)->parseCotisationText((string) $tx->communication)['insurance'];

        $variantsByMember = $members->map(fn (User $m): array => [
            'user' => $m,
            'variants' => $this->feeVariantsFor($m, $year, $insuranceHint),
        ])->all();

        $combos = [];
        $this->collectMatchingCombinations($variantsByMember, 0, [], 0.0, (float) $tx->amount, $combos);
        if ($combos === []) {
            return null;
        }

        $corroborated = $this->communicationNamesAtLeastTwo((string) $tx->communication, $members);
        $candidates = array_map(fn (array $breakdown): array => [
            'breakdown' => $breakdown,
            'total' => round(array_sum(array_column($breakdown, 'amount')), 2),
            'confidence' => $this->familyConfidence($breakdown, $year, count($combos), $corroborated),
        ], $combos);

        return ['members' => $members, 'candidates' => $candidates];
    }

    /**
     * Base cotisation, plus (only) the one insurance tier hinted at in the
     * communication text, if any — not every tier, which would blow up the
     * combination search for no real benefit (a shared lump-sum reference
     * realistically names at most one insurance tier, if it names any).
     *
     * @return list<array{label: string, amount: float}>
     */
    private function feeVariantsFor(User $member, string $year, ?string $insuranceHint): array
    {
        $fees = app(FeeCalculationService::class);
        $variants = [['label' => 'base', 'amount' => round($fees->calculate($member, $year)['amount_due'], 2)]];

        if ($insuranceHint && $insuranceHint !== 'none') {
            $slug = "ass_{$insuranceHint}";
            $variants[] = ['label' => $slug, 'amount' => round($fees->calculate($member, $year, [$slug])['amount_due'], 2)];
        }

        return $variants;
    }

    /**
     * @param  list<array{user: User, variants: list<array{label: string, amount: float}>}>  $variantsByMember
     * @param  list<array{user: User, label: string, amount: float}>  $chosen
     * @param  list<list<array{user: User, label: string, amount: float}>>  $results
     */
    private function collectMatchingCombinations(array $variantsByMember, int $i, array $chosen, float $sum, float $target, array &$results): void
    {
        if ($i === count($variantsByMember)) {
            if (abs($sum - $target) < 0.005) {
                $results[] = $chosen;
            }

            return;
        }

        foreach ($variantsByMember[$i]['variants'] as $variant) {
            $this->collectMatchingCombinations(
                $variantsByMember, $i + 1,
                [...$chosen, ['user' => $variantsByMember[$i]['user'], 'label' => $variant['label'], 'amount' => $variant['amount']]],
                $sum + $variant['amount'], $target, $results
            );
        }
    }

    /** Whether at least 2 of the family's members are nameable in the free-text communication. */
    private function communicationNamesAtLeastTwo(string $communication, Collection $members): bool
    {
        $compact = mb_strtolower(preg_replace('/[^a-z]/i', '', Str::ascii($communication)) ?? '');
        $named = $members->filter(function (User $m) use ($compact): bool {
            $last = mb_strtolower(Str::ascii((string) $m->detail?->last_name));

            return $last !== '' && str_contains($compact, $last);
        });

        return $named->count() >= 2;
    }

    /**
     * 100 only when this is the one candidate AND the communication names at
     * least 2 family members AND at least one member in the breakdown
     * already had a prior (non-backfilled) commitment matching their share.
     * 60 when unique but missing one of those two signals. 30 when more than
     * one combination fits the amount — ambiguous, never auto-picked.
     *
     * @param  list<array{user: User, label: string, amount: float}>  $breakdown
     */
    private function familyConfidence(array $breakdown, string $year, int $totalCombos, bool $corroborated): int
    {
        if ($totalCombos > 1) {
            return 30;
        }

        $hasPriorCommit = collect($breakdown)->contains(fn (array $row): bool => PaymentExpected::where('user_id', $row['user']->id)
            ->where('season_year', $year)
            ->whereIn('status', ['pending', 'partial'])
            ->whereBetween('amount_due', [$row['amount'] - 0.005, $row['amount'] + 0.005])
            ->exists());

        return $corroborated && $hasPriorCommit ? 100 : 60;
    }

    /**
     * Creates one PaymentExpected per breakdown row, all marked paid from
     * this single transaction — confirmMatch() can't be reused as-is since
     * BankTransaction.matched_payment_id links only one payment; this calls
     * MembershipRenewalService::recordSeasonPaid() per row instead, so every
     * family member's cotisation_years still gets updated. The transaction
     * itself links to the first created payment (so existing single-link UI
     * still has somewhere to point) and is marked confirmed.
     *
     * @param  array{breakdown: list<array{user: User, label: string, amount: float}>, total: float, confidence: int}  $candidate
     */
    public function createAndConfirmFamilyBackfill(BankTransaction $tx, array $candidate): void
    {
        $year = (string) Season::currentDuesYear();
        $created = [];

        foreach ($candidate['breakdown'] as $row) {
            $payment = PaymentExpected::create([
                'user_id' => $row['user']->id,
                'type' => 'membership',
                'season_year' => $year,
                'amount_due' => $row['amount'],
                'amount_paid' => $row['amount'],
                'status' => 'paid',
                'paid_at' => $tx->transaction_date,
                'reconciled_by' => auth()->user()?->name,
                'reconciled_at' => now(),
                'bank_statement_ref' => $tx->statement_ref ?? $tx->transaction_ref,
                'bank_statement_date' => $tx->transaction_date,
                'note' => "Backfilled as part of a family lump payment — bank transaction #{$tx->id} (total €{$tx->amount}, this share €{$row['amount']}).",
            ]);

            app(MembershipRenewalService::class)->recordSeasonPaid($payment->fresh());
            $created[] = $payment;
        }

        $tx->update(['matched_payment_id' => $created[0]->id, 'status' => 'confirmed', 'confirmed_by' => auth()->id()]);

        $this->learnCounterparty($tx, $created[0]->user);

        Log::info('Bank reconciliation family backfill created', [
            'bank_transaction_id' => $tx->id,
            'payment_expected_ids' => array_map(fn (PaymentExpected $p): int => $p->id, $created),
            'amount' => $tx->amount,
        ]);
    }

    /**
     * A human just confirmed this transaction's bank account/name belongs to
     * this member — remember it in ledger_counterparties (shared with the
     * Ledger's own matcher, see LedgerClassificationService::matchCounterparty())
     * so the next transaction from the same account scores a match on its own.
     */
    public function learnCounterparty(BankTransaction $tx, ?User $user): void
    {
        if (! $user || ! $tx->counterparty) {
            return;
        }

        $normalizedIban = $this->normalizeIban($tx->counterparty);
        $looksLikeIban = (bool) preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $normalizedIban);

        $counterparty = $looksLikeIban
            ? LedgerCounterparty::whereRaw('UPPER(REPLACE(iban, \' \', \'\')) = ?', [$normalizedIban])->first()
            : LedgerCounterparty::whereRaw('LOWER(name) = ?', [mb_strtolower($tx->counterparty)])->first();
        $counterparty ??= LedgerCounterparty::firstOrNew(['member_id' => $user->id, 'kind' => LedgerCounterparty::KIND_MEMBER]);

        $counterparty->fill([
            'name' => $looksLikeIban ? ($counterparty->name ?: $user->name) : $tx->counterparty,
            'iban' => $looksLikeIban ? $normalizedIban : $counterparty->iban,
            'kind' => LedgerCounterparty::KIND_MEMBER,
            'member_id' => $user->id,
        ])->save();
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

        // Known account: a previously confirmed transaction from this same
        // IBAN or name was linked to this member (see learnCounterparty()).
        if ($tx->counterparty && $pe->user_id) {
            $normalizedCounterparty = $this->normalizeIban($tx->counterparty);
            $known = LedgerCounterparty::where('member_id', $pe->user_id)
                ->where(fn ($q) => $q->whereRaw('UPPER(REPLACE(iban, \' \', \'\')) = ?', [$normalizedCounterparty])
                    ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($tx->counterparty)]))
                ->exists();
            if ($known) {
                $score += 50;
            }
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
