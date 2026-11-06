<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LedgerCounterparty;
use App\Models\LedgerOperation;
use App\Models\LedgerTag;
use App\Models\LedgerTransaction;
use App\Models\MemberDetail;
use App\Models\PaymentExpected;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * First pass on a freshly-imported line: who is it (match or create a
 * LedgerCounterparty, by IBAN first, then by member name), what is it
 * (a category + fixed tag from config('ledger.rules')), and how sure the
 * system is (the inbox "state" — see LedgerTransaction). Never auto-assigns
 * an operation (trip/loop): config('ledger.groups') only leaves a
 * `suggested_group` name for a human to confirm, matching the "propose,
 * never auto-confirm" rule used everywhere else AI/rules touch money.
 */
class LedgerClassificationService
{
    public function classify(LedgerTransaction $tx): void
    {
        $counterparty = $this->matchCounterparty($tx);
        $rule = $this->matchRule($tx);

        $tx->counterparty_id = $counterparty?->id;
        $tx->category = $rule['category'] ?? $counterparty?->default_category;
        $tx->suggested_group = $this->matchGroup($tx);

        [$state, $reason] = $this->determineState($tx, $counterparty, $rule);
        $tx->state = $state;
        $tx->state_reason = $reason;
        $tx->save();

        // The cosmetic text-only guess isn't used for $state (see
        // deriveCotisationDetails()'s own docblock), but it's logged here
        // alongside the amount-based state so a human watching an import
        // can cross-check the two signals against each other at a glance.
        $cotisationGuess = $tx->amount > 0 ? $this->deriveCotisationDetails($tx) : null;
        Log::info('Ledger transaction classified', [
            'ledger_transaction_id' => $tx->id,
            'amount' => $tx->amount,
            'communication' => $tx->communication(),
            'counterparty' => $counterparty?->name,
            'member_id' => $counterparty?->member_id,
            'rule_tag' => $rule['tag'] ?? null,
            'state' => $state,
            'state_reason' => $reason,
            'communication_category' => $cotisationGuess['categoryLabel'] ?? null,
            'communication_insurance' => $cotisationGuess['insuranceLabel'] ?? null,
        ]);

        if ($rule !== null) {
            $tag = LedgerTag::where('slug', $rule['tag'])->first();
            if ($tag) {
                $tx->tags()->syncWithoutDetaching([$tag->id]);
            }
        }
    }

    /**
     * Re-checks an amber/red line after a manual action (tag, untag, group change)
     * — the only path that can raise it to green, or refresh its explanation with
     * what's now known, without redoing the from-scratch text classification
     * (which would blow away a manual override that no longer text-matches a
     * rule). A confirmed line is left alone — it's already reviewed and closed.
     */
    public function reevaluate(LedgerTransaction $tx): void
    {
        if ($tx->confirmed_at) {
            return;
        }

        $tx->loadMissing(['tags', 'operations']);

        if (! $tx->counterparty_id) {
            $tx->counterparty_id = $this->matchCounterparty($tx)?->id;
        }
        $tx->load('counterparty');

        // A closed loop that nets to zero is settled, whatever led it there — and
        // since it's this transaction joining that may be what just balanced it,
        // every sibling in the loop is updated too, not only the one passed in
        // (the other side could have been added first, before the loop closed).
        $loop = $tx->operations->first(fn (LedgerOperation $op): bool => $op->kind === LedgerOperation::KIND_LOOP);
        if ($loop && $loop->transactions()->count() >= 2 && abs($loop->net()) < 0.01) {
            $loop->transactions()->get()->each(function (LedgerTransaction $sibling) use ($loop): void {
                if ($sibling->confirmed_at) {
                    return;
                }
                $sibling->state = LedgerTransaction::STATE_LOOP;
                $sibling->state_reason = "Part of the closed loop \"{$loop->name}\" — nets to zero.";
                $sibling->save();
            });

            return;
        }

        // A human-applied tag is at least as strong a signal as a text-matched
        // rule — tagged plus a known counterparty is "recognised" (light green).
        $tag = $tx->tags->first();
        if ($tag && $tx->counterparty_id) {
            $tx->state = LedgerTransaction::STATE_RECOGNISED;
            $tx->state_reason = "Tagged #{$tag->label}, known counterparty ({$tx->counterparty?->name}) — no amount to check against.";
            $tx->save();

            return;
        }

        $bits = [];
        if ($tag) {
            $bits[] = 'tagged '.$tx->tags->map(fn (LedgerTag $t): string => '#'.$t->label)->implode(', ');
        }
        if ($tx->operations->isNotEmpty()) {
            $bits[] = 'grouped under '.$tx->operations->pluck('name')->implode(', ');
        }
        if ($tx->counterparty_id) {
            $bits[] = 'counterparty known';
        }

        if ($bits === []) {
            return; // nothing new — leave state and state_reason as they were
        }

        $tx->state = LedgerTransaction::STATE_CONFIRM;
        $tx->state_reason = ucfirst(implode('; ', $bits)).' — still needs a human check.';
        $tx->save();
    }

    /** @var Collection<int, MemberDetail>|null memoized for the lifetime of this instance, so a batch import doesn't re-fetch every member per row */
    private ?Collection $memberDetails = null;

    /**
     * IBAN is the strongest signal (an account number doesn't change name).
     * Failing that, a name match against an existing counterparty, then
     * against a member — a member match auto-creates the counterparty row
     * so it's remembered (and correctable) from here on.
     */
    private function matchCounterparty(LedgerTransaction $tx): ?LedgerCounterparty
    {
        $iban = $tx->beneficiary_account ? strtoupper(preg_replace('/\s+/', '', $tx->beneficiary_account)) : null;
        if ($iban) {
            $byIban = LedgerCounterparty::whereRaw('UPPER(REPLACE(iban, \' \', \'\')) = ?', [$iban])->first();
            if ($byIban) {
                return $byIban;
            }
        }

        $name = $tx->counterparty_name ?: $tx->communication_1;
        if (! $name) {
            return null;
        }

        $byName = LedgerCounterparty::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($byName) {
            return $byName;
        }

        $member = $this->matchMember($name);
        if ($member) {
            return LedgerCounterparty::create([
                'name' => $name, 'iban' => $tx->beneficiary_account, 'kind' => LedgerCounterparty::KIND_MEMBER, 'member_id' => $member->user_id,
            ]);
        }

        return null;
    }

    /** Same two-words-in-common heuristic used to match bank payees to members for the IBAN fill. */
    public function matchMember(string $name): ?MemberDetail
    {
        $norm = fn (string $s): string => preg_replace('/[^a-z ]/', '', mb_strtolower(Str::ascii($s))) ?? '';
        $words = array_filter(explode(' ', $norm($name)), fn (string $w): bool => mb_strlen($w) > 2);
        if ($words === []) {
            return null;
        }

        $this->memberDetails ??= MemberDetail::query()->get();
        $hits = $this->memberDetails->filter(function (MemberDetail $d) use ($norm, $words): bool {
            $full = $norm(($d->last_name ?? '').' '.($d->first_name ?? ''));
            $matches = 0;
            foreach ($words as $w) {
                if (str_contains($full, $w)) {
                    $matches++;
                }
            }

            return $matches >= 2;
        });

        return $hits->count() === 1 ? $hits->first() : null;
    }

    /** @return array{tag: string, category: string, direction: string}|null */
    private function matchRule(LedgerTransaction $tx): ?array
    {
        $text = mb_strtolower($tx->counterparty_name.' '.$tx->communication());
        $direction = $tx->amount > 0 ? 'in' : 'out';

        foreach (config('ledger.rules', []) as $rule) {
            if ($rule['direction'] !== 'any' && $rule['direction'] !== $direction) {
                continue;
            }
            if (preg_match($rule['pattern'], $text) === 1) {
                return $rule;
            }
        }

        return null;
    }

    private function matchGroup(LedgerTransaction $tx): ?string
    {
        $text = $tx->counterparty_name.' '.$tx->communication();
        foreach (config('ledger.groups', []) as $name => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @param  array{tag: string, category: string, direction: string}|null  $rule
     * @return array{0: string, 1: string|null}
     */
    private function determineState(LedgerTransaction $tx, ?LedgerCounterparty $counterparty, ?array $rule): array
    {
        if ($rule !== null && $rule['tag'] === 'cotisation' && $counterparty?->member_id) {
            $match = PaymentExpected::where('user_id', $counterparty->member_id)->where('type', 'membership')
                ->whereIn('status', ['pending', 'partial'])
                ->get()->first(fn (PaymentExpected $pe): bool => abs((float) $pe->amount_due - (float) $tx->amount) < 0.005);

            if ($match) {
                return [LedgerTransaction::STATE_EXPECTED, 'Matches the amount this member committed to for '.$match->season_year.'.'];
            }

            return [LedgerTransaction::STATE_CONFIRM, "Recognised as a membership payment from {$counterparty->name}, but the amount doesn't match a known due."];
        }

        if ($rule !== null && $counterparty !== null) {
            return [LedgerTransaction::STATE_RECOGNISED, ucfirst($rule['tag']).' from a known counterparty — no amount to check against.'];
        }

        if ($rule !== null) {
            return [LedgerTransaction::STATE_CONFIRM, 'Looks like a '.str_replace('_', ' ', $rule['tag']).', but the counterparty isn\'t recognised yet.'];
        }

        if ($counterparty !== null) {
            return [LedgerTransaction::STATE_CONFIRM, "Known counterparty ({$counterparty->name}), but nothing recognised what this payment is for."];
        }

        return [LedgerTransaction::STATE_UNKNOWN, 'Neither the counterparty nor the purpose is recognised.'];
    }

    /**
     * Best-effort read of which cotisation category and insurance option a
     * payment was for, straight from the bank communication text — which,
     * for this club, is usually the member typing back the club's own
     * payment instructions almost verbatim (e.g. "Cotisation 2026
     * Fonctionnaire Loisir 1 Top"). A fallback for the rare line where the
     * text is too vague would need to reverse-match the paid amount against
     * every cep+licence+insurance combination for that season instead — not
     * implemented here, since text matching alone covers the large majority.
     *
     * @return array{category: string|null, categoryLabel: string|null, insurance: string|null, insuranceLabel: string|null}
     */
    public function deriveCotisationDetails(LedgerTransaction $tx): array
    {
        $text = $tx->communication_1.' '.$tx->communication_2.' '.$tx->communication_3.' '.$tx->communication_4;

        ['category' => $category, 'insurance' => $insurance] = $this->parseCotisationText($text);

        return [
            'category' => $category,
            'categoryLabel' => $category ? config("cotisation.cep.{$category}.label") : null,
            'insurance' => $insurance,
            'insuranceLabel' => $insurance ? config("cotisation.insurance.{$insurance}.label") : null,
        ];
    }

    /**
     * The same category/insurance text parsing, against any plain string —
     * so callers with a single communication field (e.g. BankTransaction)
     * can reuse it, not just LedgerTransaction's 4 communication columns.
     *
     * Real bank communications wrap/space words irregularly mid-word (seen
     * live: "ass-lois ir1top" for "loisir1top", splitting "loisir" itself)
     * — whitespace is stripped before matching, and the patterns below
     * assume that, so any such mid-word gap is tolerated rather than
     * silently losing the insurance tier (and its amount).
     *
     * @return array{category: string|null, insurance: string|null}
     */
    public function parseCotisationText(string $text): array
    {
        $compact = preg_replace('/\s+/', '', $text) ?? '';

        $category = $this->matchFirst($compact, [
            'fonctionnaire' => '/fonctionn?aire/i',
            'jeune16' => '/jeune16/i',
            'jeune' => '/jeune/i',
            'enfant' => '/enfant/i',
            'externe' => '/externe/i',
            'sympathisant' => '/sympathisant/i',
        ]);

        // Checked most-specific (Top) first — "Loisir 1" is a substring of "Loisir 1 Top".
        $insurance = $this->matchFirst($compact, [
            'loisir1top' => '/loisir1top/i',
            'loisir2top' => '/loisir2top/i',
            'loisir3top' => '/loisir3top/i',
            'loisir1' => '/loisir1(?!top)/i',
            'loisir2' => '/loisir2(?!top)/i',
            'loisir3' => '/loisir3(?!top)/i',
            'none' => '/(sans|pasd\').{0,15}assurance/i',
        ]);

        return ['category' => $category, 'insurance' => $insurance];
    }

    /** @param  array<string, string>  $patterns  key => regex, checked in order, first match wins */
    private function matchFirst(string $text, array $patterns): ?string
    {
        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return $key;
            }
        }

        return null;
    }
}
