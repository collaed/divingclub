<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LedgerTag;
use App\Models\LedgerTransaction;
use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\User;
use Database\Seeders\LedgerTagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Linking a cotisation-tagged bank transaction to a member from their own
 * profile screen (Renewal tab) — same access boundary as the ledger itself
 * (bureau_master only), and never exclusive: a couple or a parent and child
 * can pay both cotisations in one transfer, so linking it to one member must
 * not remove it as a candidate for another.
 */
#[Group('p1')]
class ProfileCotisationLinkingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(LedgerTagSeeder::class);
    }

    private function member(): User
    {
        $status = MemberStatus::firstOrCreate(['slug' => 'active'], ['name' => 'Active']);
        $user = User::factory()->create(['status_id' => $status->id]);
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'Marie', 'last_name' => 'Dupont']);
        $user->assignRole('member');

        return $user;
    }

    private function cotisationTx(array $attrs = []): LedgerTransaction
    {
        static $n = 0;
        $n++;
        $tx = LedgerTransaction::create($attrs + [
            'transaction_date' => '2026-01-0'.min($n, 9), 'amount' => 182.70, 'dedup_hash' => 'hash'.$n, 'state' => LedgerTransaction::STATE_CONFIRM,
        ]);
        $tx->tags()->attach(LedgerTag::where('slug', 'cotisation')->firstOrFail()->id);

        return $tx;
    }

    public function test_bureau_master_sees_unlinked_cotisation_lines_by_default(): void
    {
        $member = $this->member();
        $this->cotisationTx(['communication_1' => 'Cotisation 2026 Externe Loisir 1']);

        $this->actingAs($this->createBureauUser())->get(route('admin.profile.show', $member).'?tab=renewal')
            ->assertOk()->assertSee('Cotisation 2026 Externe Loisir 1')->assertSee('Loisir 1');
    }

    public function test_bureau_finance_does_not_see_the_cotisation_section(): void
    {
        $member = $this->member();
        $this->cotisationTx(['communication_1' => 'Cotisation 2026 Externe Loisir 1']);

        $finance = User::factory()->create(['email_verified_at' => now()]);
        MemberDetail::create(['user_id' => $finance->id, 'first_name' => 'F', 'last_name' => 'B']);
        $finance->assignRole('bureau_finance');

        $this->actingAs($finance)->get(route('admin.profile.show', $member).'?tab=renewal')
            ->assertOk()->assertDontSee('Cotisation bank payments');
    }

    public function test_linking_a_transaction_creates_the_pivot_row(): void
    {
        $bureau = $this->createBureauUser();
        $member = $this->member();
        $tx = $this->cotisationTx();

        $this->actingAs($bureau)->post(route('admin.profile.cotisation.link', [$member, $tx]))->assertRedirect();

        $this->assertDatabaseHas('ledger_transaction_member', ['ledger_transaction_id' => $tx->id, 'user_id' => $member->id, 'linked_by' => $bureau->id]);
    }

    public function test_a_non_bureau_master_cannot_link(): void
    {
        $member = $this->member();
        $tx = $this->cotisationTx();

        $finance = User::factory()->create(['email_verified_at' => now()]);
        MemberDetail::create(['user_id' => $finance->id, 'first_name' => 'F', 'last_name' => 'B']);
        $finance->assignRole('bureau_finance');

        $this->actingAs($finance)->post(route('admin.profile.cotisation.link', [$member, $tx]))->assertForbidden();
        $this->assertDatabaseCount('ledger_transaction_member', 0);
    }

    public function test_unlinking_removes_the_pivot_row(): void
    {
        $bureau = $this->createBureauUser();
        $member = $this->member();
        $tx = $this->cotisationTx();
        $member->cotisationTransactions()->attach($tx->id);

        $this->actingAs($bureau)->delete(route('admin.profile.cotisation.unlink', [$member, $tx]))->assertRedirect();

        $this->assertDatabaseMissing('ledger_transaction_member', ['ledger_transaction_id' => $tx->id, 'user_id' => $member->id]);
    }

    public function test_a_linked_transaction_is_hidden_by_default_but_shown_with_the_toggle(): void
    {
        $bureau = $this->createBureauUser();
        $member = $this->member();
        $tx = $this->cotisationTx(['communication_1' => 'Already linked cotisation']);
        $member->cotisationTransactions()->attach($tx->id);

        $this->actingAs($bureau)->get(route('admin.profile.show', $member).'?tab=renewal')
            ->assertOk()->assertDontSee('Already linked cotisation');

        $this->actingAs($bureau)->get(route('admin.profile.show', $member).'?tab=renewal&show_identified=1')
            ->assertOk()->assertSee('Already linked cotisation');
    }

    public function test_a_transaction_linked_to_one_member_still_shows_as_a_candidate_for_another(): void
    {
        $bureau = $this->createBureauUser();
        $memberA = $this->member();
        $memberB = $this->member();
        $tx = $this->cotisationTx(['communication_1' => 'Shared family transfer']);
        $memberA->cotisationTransactions()->attach($tx->id);

        $this->actingAs($bureau)->get(route('admin.profile.show', $memberB).'?tab=renewal')
            ->assertOk()->assertSee('Shared family transfer');
    }

    public function test_search_filters_by_communication_text(): void
    {
        $bureau = $this->createBureauUser();
        $member = $this->member();
        $this->cotisationTx(['communication_1' => 'Cotisation Jean Dupont']);
        $this->cotisationTx(['communication_1' => 'Cotisation Paul Martin']);

        $response = $this->actingAs($bureau)->get(route('admin.profile.show', $member).'?tab=renewal&cot_search=Dupont');

        $response->assertOk()->assertSee('Cotisation Jean Dupont')->assertDontSee('Cotisation Paul Martin');
    }
}
