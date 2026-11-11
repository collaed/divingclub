<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\PaymentExpected;
use App\Models\Season;
use App\Models\User;
use App\Services\BankReconciliationService;
use Database\Seeders\Fee2027Seeder;
use Database\Seeders\MemberStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * A member who pays their cotisation without ever clicking "I commit" on
 * the renewal flow leaves no PaymentExpected to reconcile against — today
 * a dead end on the transaction-detail page. This backfills it from the
 * real price list (never trusting the paid amount blindly), and only
 * offers the one-click fix when that derivation matches what was paid.
 */
class BankReconciliationBackfillTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private BankReconciliationService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(MemberStatusSeeder::class);
        Season::factory()->create(['year' => '2027', 'start_date' => '2026-09-01', 'fee_taper_tiers' => null]);
        $this->seed(Fee2027Seeder::class);
        $this->svc = app(BankReconciliationService::class);
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

    public function test_cep_reference_resolves_the_member_even_with_noisy_surrounding_text(): void
    {
        $user = $this->member('fonctionnaire', 45);
        $tx = BankTransaction::create([
            'transaction_date' => now(), 'amount' => 228.00,
            'communication' => "CEP-2027-{$user->id}-SCHMID Jurgen+ass-lois ir1top JURGEN SCHMID",
            'status' => 'unmatched',
        ]);

        $proposal = $this->svc->proposeBackfill($tx);

        $this->assertNotNull($proposal);
        $this->assertSame($user->id, $proposal['user']->id);
        $this->assertSame('reference', $proposal['matched_by']);
        $this->assertTrue($proposal['clean_match']);
        $this->assertSame(228.00, $proposal['amount_due']);
    }

    public function test_clean_match_creates_and_confirms_via_the_controller_action(): void
    {
        $bureau = $this->createBureauUser();
        $user = $this->member('fonctionnaire', 45);
        $tx = BankTransaction::create([
            'transaction_date' => now(), 'amount' => 228.00,
            'communication' => "CEP-2027-{$user->id}-SCHMID Jurgen+ass-lois ir1top JURGEN SCHMID",
            'status' => 'unmatched',
        ]);

        $this->assertSame(0, PaymentExpected::where('user_id', $user->id)->count());

        $this->actingAs($bureau)->post(route('admin.payments.backfill-match', $tx))->assertRedirect();

        $tx->refresh();
        $this->assertSame('confirmed', $tx->status);
        $pe = PaymentExpected::where('user_id', $user->id)->where('type', 'membership')->first();
        $this->assertNotNull($pe);
        $this->assertSame(228.00, (float) $pe->amount_due);
        $this->assertSame(228.00, (float) $pe->amount_paid);
        $this->assertSame('paid', $pe->status);
        $this->assertSame($pe->id, $tx->matched_payment_id);
        $this->assertStringContainsString('Backfilled from bank transaction', (string) $pe->note);
        $this->assertContains('2027', $user->fresh()->detail->cotisation_years ?? []);
    }

    public function test_an_amount_that_does_not_decompose_cleanly_is_refused(): void
    {
        $bureau = $this->createBureauUser();
        $user = $this->member('fonctionnaire', 45);
        $tx = BankTransaction::create([
            'transaction_date' => now(), 'amount' => 999.00,
            'communication' => "CEP-2027-{$user->id}-SCHMID Jurgen+ass-lois ir1top JURGEN SCHMID",
            'status' => 'unmatched',
        ]);

        $proposal = $this->svc->proposeBackfill($tx);
        $this->assertNotNull($proposal);
        $this->assertFalse($proposal['clean_match']);

        $this->actingAs($bureau)->post(route('admin.payments.backfill-match', $tx))->assertRedirect();

        $this->assertSame('unmatched', $tx->refresh()->status);
        $this->assertSame(0, PaymentExpected::where('user_id', $user->id)->count());
    }

    public function test_no_resolvable_member_returns_null(): void
    {
        $tx = BankTransaction::create([
            'transaction_date' => now(), 'amount' => 100.00,
            'communication' => 'completely unrelated memo text',
            'status' => 'unmatched',
        ]);

        $this->assertNull($this->svc->proposeBackfill($tx));
    }

    public function test_a_surname_alone_with_no_forename_still_resolves_when_unique(): void
    {
        $user = $this->member('fonctionnaire', 45);
        $tx = BankTransaction::create([
            'transaction_date' => now(), 'amount' => 999.00,
            // Real-world French cotisation wording: only a civility + surname,
            // the forename never appears, and a second (unrelated) surname
            // rides along for a couple paying together.
            'communication' => 'cotisation CEP + FLASSA 2026/2027 MME SCHMID M. BERELDANGE',
            'status' => 'unmatched',
        ]);

        $proposal = $this->svc->proposeBackfill($tx);

        $this->assertNotNull($proposal);
        $this->assertSame($user->id, $proposal['user']->id);
        $this->assertSame('name', $proposal['matched_by']);
    }

    public function test_a_surname_shared_by_two_members_stays_unresolved(): void
    {
        $this->member('fonctionnaire', 45);
        $status = MemberStatus::where('slug', 'fonctionnaire')->firstOrFail();
        $other = User::factory()->create(['status_id' => $status->id]);
        MemberDetail::create(['user_id' => $other->id, 'first_name' => 'Anna', 'last_name' => 'Schmid']);

        $tx = BankTransaction::create([
            'transaction_date' => now(), 'amount' => 100.00,
            'communication' => 'cotisation MME SCHMID',
            'status' => 'unmatched',
        ]);

        $this->assertNull($this->svc->proposeBackfill($tx));
    }

    public function test_non_bureau_user_cannot_backfill(): void
    {
        $member = $this->createMemberUser();
        $user = $this->member('fonctionnaire', 45);
        $tx = BankTransaction::create([
            'transaction_date' => now(), 'amount' => 228.00,
            'communication' => "CEP-2027-{$user->id}-SCHMID Jurgen+ass-lois ir1top JURGEN SCHMID",
            'status' => 'unmatched',
        ]);

        $this->actingAs($member)->post(route('admin.payments.backfill-match', $tx))->assertForbidden();
    }
}
