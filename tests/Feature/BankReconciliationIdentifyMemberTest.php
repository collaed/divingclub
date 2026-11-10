<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\LedgerCounterparty;
use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\PaymentExpected;
use App\Models\Season;
use App\Models\User;
use App\Services\FeeCalculationService;
use Database\Seeders\Fee2027Seeder;
use Database\Seeders\MemberStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * When a transaction matches no PaymentExpected and no regex/name pattern
 * (see BankReconciliationBackfillTest), the bureau can still recognize the
 * counterparty by sight. This should always remember the account/name
 * against the chosen member (so future transactions auto-match), and only
 * ever create/confirm a payment outright when the derived fee cleanly
 * matches what was actually paid.
 */
class BankReconciliationIdentifyMemberTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(MemberStatusSeeder::class);
        Season::factory()->create(['year' => '2027', 'start_date' => '2026-09-01', 'fee_taper_tiers' => null]);
        $this->seed(Fee2027Seeder::class);
    }

    private function member(string $statusSlug, int $ageAtAnchor): User
    {
        $status = MemberStatus::where('slug', $statusSlug)->firstOrFail();
        $user = User::factory()->create(['status_id' => $status->id]);
        MemberDetail::create([
            'user_id' => $user->id,
            'first_name' => 'Jurgen',
            'last_name' => 'Schmid',
            'date_of_birth' => Carbon::createFromDate(2026, 9, 1)->subYears($ageAtAnchor)->toDateString(),
        ]);

        return $user->fresh(['detail', 'status']);
    }

    public function test_a_clean_match_creates_and_confirms_the_payment(): void
    {
        $bureau = $this->createBureauUser();
        $user = $this->member('fonctionnaire', 45);
        $amountDue = (float) app(FeeCalculationService::class)->calculate($user, '2027')['amount_due'];
        $tx = BankTransaction::create([
            'transaction_date' => now(), 'amount' => $amountDue,
            'communication' => 'no pattern here at all', 'counterparty' => 'Account of J. Schmid',
            'status' => 'unmatched',
        ]);

        $this->actingAs($bureau)->post(route('admin.payments.transactions.identify', $tx), [
            'user_id' => $user->id,
        ])->assertRedirect();

        $tx->refresh();
        $this->assertSame('confirmed', $tx->status);
        $pe = PaymentExpected::where('user_id', $user->id)->where('type', 'membership')->first();
        $this->assertNotNull($pe);
        $this->assertSame('paid', $pe->status);
        $this->assertNotNull(LedgerCounterparty::where('member_id', $user->id)->first());
    }

    public function test_a_mismatched_amount_still_remembers_the_counterparty_but_does_not_create_a_payment(): void
    {
        $bureau = $this->createBureauUser();
        $user = $this->member('fonctionnaire', 45);
        $tx = BankTransaction::create([
            'transaction_date' => now(), 'amount' => 999.00,
            'communication' => 'no pattern here at all', 'counterparty' => 'Account of J. Schmid',
            'status' => 'unmatched',
        ]);

        $this->actingAs($bureau)->post(route('admin.payments.transactions.identify', $tx), [
            'user_id' => $user->id,
        ])->assertRedirect();

        $this->assertSame('unmatched', $tx->refresh()->status);
        $this->assertSame(0, PaymentExpected::where('user_id', $user->id)->count());

        $counterparty = LedgerCounterparty::where('member_id', $user->id)->first();
        $this->assertNotNull($counterparty);
        $this->assertSame('Account of J. Schmid', $counterparty->name);
    }

    public function test_a_confirmed_transaction_cannot_be_identified_after_the_fact(): void
    {
        $bureau = $this->createBureauUser();
        $user = $this->member('fonctionnaire', 45);
        $tx = BankTransaction::create([
            'transaction_date' => now(), 'amount' => 228.00,
            'communication' => 'x', 'status' => 'confirmed',
        ]);

        $this->actingAs($bureau)->post(route('admin.payments.transactions.identify', $tx), [
            'user_id' => $user->id,
        ])->assertRedirect();

        $this->assertNull(LedgerCounterparty::where('member_id', $user->id)->first());
    }

    public function test_non_bureau_user_cannot_identify_a_counterparty(): void
    {
        $member = $this->createMemberUser();
        $user = $this->member('fonctionnaire', 45);
        $tx = BankTransaction::create([
            'transaction_date' => now(), 'amount' => 228.00,
            'communication' => 'x', 'status' => 'unmatched',
        ]);

        $this->actingAs($member)->post(route('admin.payments.transactions.identify', $tx), [
            'user_id' => $user->id,
        ])->assertForbidden();
    }
}
