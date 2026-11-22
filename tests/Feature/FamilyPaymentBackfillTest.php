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
use App\Services\FeeCalculationService;
use Database\Seeders\Fee2027Seeder;
use Database\Seeders\MemberStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * A family often pays one bank transfer covering several members' cotisations
 * combined — today's proposeBackfill() only ever resolves a single person.
 * This always requires bureau review (never auto-applies), but surfaces a
 * confidence score: 100 only when there is exactly one matching breakdown,
 * the communication names at least 2 of the family, and at least one member
 * already had a prior (non-backfilled) commitment matching their share.
 */
class FamilyPaymentBackfillTest extends TestCase
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

    private function member(string $statusSlug, int $ageAtAnchor, string $lastName, int $familyId): User
    {
        $status = MemberStatus::where('slug', $statusSlug)->firstOrFail();
        $user = User::factory()->create(['status_id' => $status->id]);
        MemberDetail::create([
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => $lastName,
            'date_of_birth' => Carbon::createFromDate(2026, 9, 1)->subYears($ageAtAnchor)->toDateString(),
            'family_id' => $familyId,
        ]);

        return $user->fresh(['detail', 'status']);
    }

    private function baseFee(User $user): float
    {
        return round(app(FeeCalculationService::class)->calculate($user, '2027')['amount_due'], 2);
    }

    public function test_no_family_returns_null(): void
    {
        $tx = BankTransaction::create(['transaction_date' => now(), 'amount' => 100.00, 'communication' => 'unresolvable memo', 'status' => 'unmatched']);

        $this->assertNull($this->svc->proposeFamilyBackfill($tx));
    }

    public function test_solo_member_with_no_family_returns_null_even_with_a_reference(): void
    {
        $status = MemberStatus::where('slug', 'fonctionnaire')->firstOrFail();
        $user = User::factory()->create(['status_id' => $status->id]);
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'Solo', 'last_name' => 'Nobody']);

        $tx = BankTransaction::create([
            'transaction_date' => now(), 'amount' => 100.00,
            'communication' => "CEP-2027-{$user->id}-NOBODY SOLO", 'status' => 'unmatched',
        ]);

        $this->assertNull($this->svc->proposeFamilyBackfill($tx));
    }

    public function test_unique_combination_with_no_corroboration_scores_60(): void
    {
        $a = $this->member('fonctionnaire', 45, 'Durand', 900);
        $b = $this->member('externe', 20, 'Martin', 900);
        $total = $this->baseFee($a) + $this->baseFee($b);

        $tx = BankTransaction::create([
            'transaction_date' => now(), 'amount' => $total,
            'communication' => "CEP-2027-{$a->id}-FAMILY PAYMENT", 'status' => 'unmatched',
        ]);

        $proposal = $this->svc->proposeFamilyBackfill($tx);

        $this->assertNotNull($proposal);
        $this->assertCount(1, $proposal['candidates']);
        $this->assertSame(60, $proposal['candidates'][0]['confidence']);
    }

    public function test_unique_combination_with_corroboration_and_prior_commit_scores_100(): void
    {
        $a = $this->member('fonctionnaire', 45, 'Durand', 901);
        $b = $this->member('externe', 20, 'Martin', 901);
        $total = $this->baseFee($a) + $this->baseFee($b);

        PaymentExpected::create([
            'user_id' => $a->id, 'type' => 'membership', 'season_year' => '2027',
            'amount_due' => $this->baseFee($a), 'amount_paid' => 0, 'status' => 'pending',
        ]);

        $tx = BankTransaction::create([
            'transaction_date' => now(), 'amount' => $total,
            'communication' => "CEP-2027-{$a->id}-DURAND MARTIN FAMILLE", 'status' => 'unmatched',
        ]);

        $proposal = $this->svc->proposeFamilyBackfill($tx);

        $this->assertNotNull($proposal);
        $this->assertCount(1, $proposal['candidates']);
        $this->assertSame(100, $proposal['candidates'][0]['confidence']);
    }

    public function test_ambiguous_amount_lists_every_candidate_without_picking_one(): void
    {
        // Identical twins: same status/age, so each gets the same 2 variants
        // (base, base+insurance) — the target can be hit by either twin
        // taking the insurance, which is genuinely ambiguous.
        $a = $this->member('fonctionnaire', 45, 'Twin', 902);
        $b = $this->member('fonctionnaire', 45, 'Twin', 902);
        $base = $this->baseFee($a);
        $withInsurance = round(app(FeeCalculationService::class)->calculate($a, '2027', ['ass_loisir1top'])['amount_due'], 2);
        $this->assertNotEquals($base, $withInsurance, 'test fixture needs an insurance tier that actually changes the price');

        $tx = BankTransaction::create([
            'transaction_date' => now(), 'amount' => $base + $withInsurance,
            'communication' => "CEP-2027-{$a->id}-TWIN FAMILLE ass-lois ir1top", 'status' => 'unmatched',
        ]);

        $proposal = $this->svc->proposeFamilyBackfill($tx);

        $this->assertNotNull($proposal);
        $this->assertGreaterThan(1, count($proposal['candidates']));
        foreach ($proposal['candidates'] as $candidate) {
            $this->assertSame(30, $candidate['confidence']);
        }
    }

    public function test_controller_action_creates_one_payment_per_member_and_updates_both_cotisation_years(): void
    {
        $bureau = $this->createBureauUser();
        $a = $this->member('fonctionnaire', 45, 'Durand', 903);
        $b = $this->member('externe', 20, 'Martin', 903);
        $total = $this->baseFee($a) + $this->baseFee($b);

        $tx = BankTransaction::create([
            'transaction_date' => now(), 'amount' => $total,
            'communication' => "CEP-2027-{$a->id}-FAMILY PAYMENT", 'status' => 'unmatched',
        ]);

        $this->actingAs($bureau)
            ->post(route('admin.payments.backfill-family-match', $tx), ['candidate_index' => 0])
            ->assertRedirect();

        $tx->refresh();
        $this->assertSame('confirmed', $tx->status);
        $this->assertNotNull($tx->matched_payment_id);

        $this->assertSame('paid', PaymentExpected::where('user_id', $a->id)->first()?->status);
        $this->assertSame('paid', PaymentExpected::where('user_id', $b->id)->first()?->status);
        $this->assertContains('2027', $a->fresh()->detail->cotisation_years ?? []);
        $this->assertContains('2027', $b->fresh()->detail->cotisation_years ?? []);
    }

    public function test_stale_candidate_index_is_refused(): void
    {
        $bureau = $this->createBureauUser();
        $a = $this->member('fonctionnaire', 45, 'Durand', 904);
        $b = $this->member('externe', 20, 'Martin', 904);
        $total = $this->baseFee($a) + $this->baseFee($b);

        $tx = BankTransaction::create([
            'transaction_date' => now(), 'amount' => $total,
            'communication' => "CEP-2027-{$a->id}-FAMILY PAYMENT", 'status' => 'unmatched',
        ]);

        $this->actingAs($bureau)
            ->post(route('admin.payments.backfill-family-match', $tx), ['candidate_index' => 5])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame('unmatched', $tx->refresh()->status);
    }
}
