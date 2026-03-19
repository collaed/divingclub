<?php

namespace App\Services;

use App\Models\BankTransaction;
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
            if (count($parts) < 2) continue;

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
     * Auto-match unmatched transactions against pending payments using fuzzy communication match.
     */
    public function autoMatch(): array
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
                $tx->update(['matched_payment_id' => $bestMatch->id, 'match_score' => $bestScore, 'status' => 'matched']);
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
        if (!$payment) return;

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
        if ($name && stripos($tx->communication, $name) !== false) {
            $score += 30;
        }

        return min($score, 100);
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
