<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\PaymentExpected;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * The reconciliation list only shows 40 characters of the communication and
 * offers no way to pick a different member than whatever was suggested (or
 * nothing, if nothing was). This detail page + manual-link action fixes
 * both: full transaction text, and a search over pending dues to link to.
 */
#[Group('p1')]
class BankTransactionManualMatchTest extends TestCase
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
            'communication' => 'CEP-2027-'.$user->id, 'components' => ['membership' => $amount, 'status' => 'Test'],
            'status' => 'pending',
        ]);
    }

    public function test_show_page_lists_the_full_communication_and_candidates_by_amount_closeness(): void
    {
        $bureau = $this->createBureauUser();
        $closeMember = $this->createMemberUser();
        $farMember = $this->createMemberUser();
        $this->pendingDue($closeMember, 101.0);
        $this->pendingDue($farMember, 500.0);

        $tx = BankTransaction::create([
            'transaction_date' => now(), 'amount' => 100.0,
            'communication' => 'A much longer transfer memo than the forty characters the list page truncates to',
            'counterparty' => 'Someone', 'status' => 'unmatched',
        ]);

        $response = $this->actingAs($bureau)->get(route('admin.payments.transactions.show', $tx));

        $response->assertOk()
            ->assertSee('A much longer transfer memo than the forty characters the list page truncates to')
            ->assertSeeInOrder([$closeMember->name, $farMember->name]);
    }

    public function test_manually_linking_sets_status_suggested_and_is_then_confirmable(): void
    {
        $bureau = $this->createBureauUser();
        $member = $this->createMemberUser();
        $pe = $this->pendingDue($member, 100.0);
        $tx = BankTransaction::create(['transaction_date' => now(), 'amount' => 100.0, 'communication' => 'unclear', 'status' => 'unmatched']);

        $this->actingAs($bureau)->post(route('admin.payments.transactions.match', $tx), ['payment_id' => $pe->id])
            ->assertRedirect(route('admin.payments.transactions.show', $tx));

        $tx->refresh();
        $this->assertSame('suggested', $tx->status);
        $this->assertSame($pe->id, $tx->matched_payment_id);
        $this->assertNull($tx->match_score);
        $this->assertStringContainsString($bureau->name, (string) $tx->match_reason);

        $this->actingAs($bureau)->post(route('admin.payments.confirm-match', $tx))->assertRedirect();
        $this->assertSame('confirmed', $tx->refresh()->status);
        $this->assertSame('paid', $pe->fresh()->status);
    }

    public function test_a_confirmed_transaction_cannot_be_relinked(): void
    {
        $bureau = $this->createBureauUser();
        $member = $this->createMemberUser();
        $pe = $this->pendingDue($member, 100.0);
        $otherPe = $this->pendingDue($this->createMemberUser(), 100.0);
        $tx = BankTransaction::create([
            'transaction_date' => now(), 'amount' => 100.0, 'communication' => 'x',
            'matched_payment_id' => $pe->id, 'status' => 'confirmed',
        ]);

        $this->actingAs($bureau)->post(route('admin.payments.transactions.match', $tx), ['payment_id' => $otherPe->id]);

        $this->assertSame($pe->id, $tx->fresh()->matched_payment_id);
    }

    public function test_non_bureau_viewer_cannot_reach_the_detail_page(): void
    {
        $member = $this->createMemberUser();
        $tx = BankTransaction::create(['transaction_date' => now(), 'amount' => 100.0, 'communication' => 'x', 'status' => 'unmatched']);

        $this->actingAs($member)->get(route('admin.payments.transactions.show', $tx))->assertForbidden();
    }
}
