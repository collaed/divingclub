<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\MembershipFeeComponent;
use App\Models\PaymentExpected;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Member profile (Licence + Inscriptions tabs): bureau-visible list of this
 * member's confirmed/suggested Bank Reconciliation transactions, with a
 * membership payment's stored `PaymentExpected->components` rendered as a
 * line-item breakdown. Gated the same way as Payments -> Membership renewals
 * -> Reconciliation itself (User::isBureau()), not bureau_master-only like
 * the separate Ledger "Cotisation bank payments" block.
 */
#[Group('p1')]
class ProfileMemberTransactionsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function membershipPayment(User $user, array $components, float $amountDue): PaymentExpected
    {
        return PaymentExpected::create([
            'user_id' => $user->id, 'type' => 'membership', 'season_year' => '2027',
            'amount_due' => $amountDue, 'amount_paid' => $amountDue,
            'communication' => 'CEP-2027-'.$user->id, 'components' => $components, 'status' => 'paid',
        ]);
    }

    private function bankTx(array $attrs): BankTransaction
    {
        return BankTransaction::create($attrs + [
            'transaction_date' => now(), 'amount' => 55.00, 'communication' => 'virement', 'counterparty' => 'Test',
        ]);
    }

    public function test_bureau_sees_only_this_members_confirmed_and_suggested_transactions(): void
    {
        $bureau = $this->createBureauUser();
        $memberA = $this->createMemberUser();
        $memberB = $this->createMemberUser();

        $peA = $this->membershipPayment($memberA, ['membership' => 100.0, 'status' => 'Test'], 100.0);
        $peB = $this->membershipPayment($memberB, ['membership' => 100.0, 'status' => 'Test'], 100.0);

        $this->bankTx(['amount' => 100.0, 'communication' => 'A confirmed one', 'matched_payment_id' => $peA->id, 'status' => 'confirmed']);
        $this->bankTx(['amount' => 100.0, 'communication' => 'B confirmed one', 'matched_payment_id' => $peB->id, 'status' => 'confirmed']);
        $this->bankTx(['amount' => 100.0, 'communication' => 'A suggested one', 'matched_payment_id' => $peA->id, 'status' => 'suggested', 'match_score' => 75]);

        $response = $this->actingAs($bureau)->get(route('admin.profile.show', $memberA).'?tab=renewal');

        $response->assertOk()->assertSee('75%')->assertDontSee('B confirmed one');
    }

    public function test_non_bureau_viewer_does_not_see_the_block(): void
    {
        $member = $this->createMemberUser();
        $pe = $this->membershipPayment($member, ['membership' => 100.0, 'status' => 'Test'], 100.0);
        $this->bankTx(['amount' => 100.0, 'matched_payment_id' => $pe->id, 'status' => 'confirmed']);

        $response = $this->actingAs($member)->get(route('profile.show').'?tab=renewal');

        $response->assertOk()->assertDontSee('Identified payments')->assertDontSee('Suspected matches');
    }

    public function test_membership_payment_renders_its_component_breakdown(): void
    {
        MembershipFeeComponent::create(['slug' => 'adulte', 'name' => 'Licence FFESSM', 'kind' => MembershipFeeComponent::KIND_FFESSM_LICENCE, 'amount' => 48.50, 'is_optional' => false]);
        MembershipFeeComponent::create(['slug' => 'standard', 'name' => 'Licence FLASSA', 'kind' => MembershipFeeComponent::KIND_FLASSA, 'amount' => 10.00, 'is_optional' => false]);
        MembershipFeeComponent::create(['slug' => 'loisir1', 'name' => 'Loisir 1', 'kind' => MembershipFeeComponent::KIND_ASSURANCE, 'amount' => 25.00, 'is_optional' => true]);

        $bureau = $this->createBureauUser();
        $member = $this->createMemberUser();
        $pe = $this->membershipPayment($member, [
            'membership' => 131.50, 'status' => 'Fonctionnaire',
            'ffessm_licence' => 'adulte', 'adulte' => 48.50,
            'flassa_state' => 'required', 'standard' => 10.00,
            'loisir1' => 25.00,
        ], 215.00);
        $this->bankTx(['amount' => 215.00, 'matched_payment_id' => $pe->id, 'status' => 'confirmed']);

        $response = $this->actingAs($bureau)->get(route('admin.profile.show', $member).'?tab=renewal');

        $response->assertOk()
            ->assertSee('Licence FFESSM')->assertSee('48,50')
            ->assertSee('Licence FLASSA')->assertSee('10,00')
            ->assertSee('Loisir 1')->assertSee('25,00')
            ->assertSee('131,50');
    }

    public function test_event_payment_renders_its_stored_label_amount_list(): void
    {
        $bureau = $this->createBureauUser();
        $member = $this->createMemberUser();
        $pe = PaymentExpected::create([
            'user_id' => $member->id, 'type' => 'event', 'season_year' => '2027',
            'amount_due' => 60.0, 'amount_paid' => 60.0,
            'communication' => 'CEP-EVT-'.$member->id,
            'components' => [['label' => 'Deposit 1', 'amount' => 60.0]],
            'status' => 'paid',
        ]);
        $this->bankTx(['amount' => 60.0, 'matched_payment_id' => $pe->id, 'status' => 'confirmed']);

        $response = $this->actingAs($bureau)->get(route('admin.profile.show', $member).'?tab=renewal');

        $response->assertOk()->assertSee('Deposit 1')->assertSee('60,00');
    }
}
