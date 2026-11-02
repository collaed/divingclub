<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\LedgerCounterparty;
use App\Models\PaymentExpected;
use App\Models\User;
use App\Services\BankReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Once a bureau member confirms a match, the transaction's IBAN/name should
 * be remembered against that member (shared ledger_counterparties table, see
 * BankReconciliationService::learnCounterparty()) so the next transaction
 * from the same account scores a match on its own, without any text/amount
 * coincidence to lean on.
 */
#[Group('p1')]
class BankReconciliationCounterpartyLearningTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function pendingDue(User $user, float $amount = 100.0): PaymentExpected
    {
        return PaymentExpected::create([
            'user_id' => $user->id, 'type' => 'membership', 'season_year' => '2027',
            'amount_due' => $amount, 'amount_paid' => 0,
            'communication' => 'unrelated reference', 'components' => ['membership' => $amount],
            'status' => 'pending',
        ]);
    }

    public function test_confirming_a_match_learns_the_counterparty_iban_for_that_member(): void
    {
        $member = $this->createMemberUser();
        $pe = $this->pendingDue($member);
        $tx = BankTransaction::create([
            'transaction_date' => now(), 'amount' => 100.0, 'communication' => 'x',
            'counterparty' => 'LU12 3456 7890 1234 5678', 'matched_payment_id' => $pe->id, 'status' => 'suggested',
        ]);

        app(BankReconciliationService::class)->confirmMatch($tx);

        $counterparty = LedgerCounterparty::where('member_id', $member->id)->first();
        $this->assertNotNull($counterparty);
        $this->assertSame('LU123456789012345678', $counterparty->iban);
        $this->assertSame(LedgerCounterparty::KIND_MEMBER, $counterparty->kind);
    }

    public function test_a_later_unmatched_transaction_from_the_known_account_is_suggested_without_a_text_or_amount_match(): void
    {
        $member = $this->createMemberUser();
        $firstDue = $this->pendingDue($member, 100.0);
        $tx = BankTransaction::create([
            'transaction_date' => now(), 'amount' => 100.0, 'communication' => 'x',
            'counterparty' => 'LU12 3456 7890 1234 5678', 'matched_payment_id' => $firstDue->id, 'status' => 'suggested',
        ]);
        app(BankReconciliationService::class)->confirmMatch($tx);

        // A new due, unrelated communication, from the same account but a
        // slightly different (still close) amount — relies purely on the
        // learned counterparty plus the "close amount" sub-score to clear
        // the suggestion threshold.
        $secondDue = $this->pendingDue($member, 103.0);
        $secondTx = BankTransaction::create([
            'transaction_date' => now(), 'amount' => 103.0, 'communication' => 'no reference at all',
            'counterparty' => 'LU12 3456 7890 1234 5678', 'status' => 'unmatched',
        ]);

        app(BankReconciliationService::class)->suggestMatches();

        $secondTx->refresh();
        $this->assertSame('suggested', $secondTx->status);
        $this->assertSame($secondDue->id, $secondTx->matched_payment_id);
    }
}
