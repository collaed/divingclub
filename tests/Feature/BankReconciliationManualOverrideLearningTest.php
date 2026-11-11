<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\LedgerCounterparty;
use App\Models\PaymentExpected;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Sanity check: when the bureau overrides whatever the system suggested (or
 * suggested nothing) by hand-picking a different pending due via "Link to a
 * different member" and then confirming, the account/name should still be
 * learned (see BankReconciliationService::learnCounterparty()) — not just
 * on an auto-suggested match or the "Recognize this person" picker.
 */
class BankReconciliationManualOverrideLearningTest extends TestCase
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

    public function test_manually_linking_to_a_non_suggested_member_and_confirming_learns_the_counterparty(): void
    {
        $bureau = $this->createBureauUser();
        $suggested = $this->createMemberUser();
        $actual = $this->createMemberUser();
        $suggestedDue = $this->pendingDue($suggested);
        $actualDue = $this->pendingDue($actual);

        $tx = BankTransaction::create([
            'transaction_date' => now(), 'amount' => 100.0, 'communication' => 'no reference at all',
            'counterparty' => 'Account of the real payer', 'matched_payment_id' => $suggestedDue->id,
            'match_score' => 60, 'match_reason' => 'auto-suggested', 'status' => 'suggested',
        ]);

        // Bureau disagrees with the suggestion and relinks it by hand.
        $this->actingAs($bureau)->post(route('admin.payments.transactions.match', $tx), [
            'payment_id' => $actualDue->id,
        ])->assertRedirect();

        $this->actingAs($bureau)->post(route('admin.payments.confirm-match', $tx))->assertRedirect();

        $tx->refresh();
        $this->assertSame($actualDue->id, $tx->matched_payment_id);
        $this->assertSame('confirmed', $tx->status);

        $counterparty = LedgerCounterparty::where('member_id', $actual->id)->first();
        $this->assertNotNull($counterparty);
        $this->assertSame('Account of the real payer', $counterparty->name);
        $this->assertNull(LedgerCounterparty::where('member_id', $suggested->id)->first());
    }
}
