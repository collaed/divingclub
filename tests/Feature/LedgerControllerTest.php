<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LedgerOperation;
use App\Models\LedgerTag;
use App\Models\LedgerTransaction;
use App\Models\MemberDetail;
use App\Models\User;
use Database\Seeders\LedgerTagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

#[Group('p1')]
class LedgerControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(LedgerTagSeeder::class);
    }

    private function tx(array $attrs = []): LedgerTransaction
    {
        static $n = 0;
        $n++;

        return LedgerTransaction::create($attrs + [
            'transaction_date' => '2026-01-0'.min($n, 9), 'amount' => 100, 'dedup_hash' => 'hash'.$n, 'state' => LedgerTransaction::STATE_UNKNOWN,
        ]);
    }

    public function test_a_plain_member_cannot_open_the_ledger(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        MemberDetail::create(['user_id' => $member->id, 'first_name' => 'A', 'last_name' => 'B']);
        $member->assignRole('member');

        $this->actingAs($member)->get(route('admin.ledger.index'))->assertForbidden();
    }

    /**
     * The ledger is bureau_master only — narrower than the rest of admin.php,
     * which the other bureau roles can otherwise reach.
     */
    public function test_bureau_finance_and_bureau_technical_cannot_open_the_ledger(): void
    {
        foreach (['bureau_finance', 'bureau_technical'] as $role) {
            $user = User::factory()->create(['email_verified_at' => now()]);
            MemberDetail::create(['user_id' => $user->id, 'first_name' => 'A', 'last_name' => 'B']);
            $user->assignRole($role);

            $this->actingAs($user)->get(route('admin.ledger.index'))->assertForbidden();
        }
    }

    public function test_the_inbox_lists_unconfirmed_transactions_grouped_by_state(): void
    {
        $this->tx(['state' => LedgerTransaction::STATE_EXPECTED, 'counterparty_name' => 'Marie Dupont']);
        $this->tx(['state' => LedgerTransaction::STATE_UNKNOWN, 'counterparty_name' => 'Unknown Payer']);

        $this->actingAs($this->createBureauUser())->get(route('admin.ledger.index'))
            ->assertOk()->assertSee('Marie Dupont')->assertSee('Unknown Payer');
    }

    public function test_a_confirmed_transaction_drops_out_of_the_inbox(): void
    {
        $this->tx(['counterparty_name' => 'Already Done', 'confirmed_at' => now()]);

        $this->actingAs($this->createBureauUser())->get(route('admin.ledger.index'))->assertOk()->assertDontSee('Already Done');
    }

    public function test_confirming_a_line_stamps_who_and_when(): void
    {
        $tx = $this->tx();
        $bureau = $this->createBureauUser();

        $this->actingAs($bureau)->post(route('admin.ledger.confirm', $tx))->assertRedirect();

        $tx->refresh();
        $this->assertNotNull($tx->confirmed_at);
        $this->assertSame($bureau->id, $tx->confirmed_by);
    }

    public function test_bulk_confirm_only_confirms_trusted_states_not_unknown_or_to_confirm(): void
    {
        $expected = $this->tx(['state' => LedgerTransaction::STATE_EXPECTED]);
        $unknown = $this->tx(['state' => LedgerTransaction::STATE_UNKNOWN]);
        $confirmState = $this->tx(['state' => LedgerTransaction::STATE_CONFIRM]);

        $this->actingAs($this->createBureauUser())->post(route('admin.ledger.bulk-confirm'), [
            'ids' => [$expected->id, $unknown->id, $confirmState->id],
        ])->assertRedirect();

        $this->assertNotNull($expected->fresh()->confirmed_at);
        $this->assertNull($unknown->fresh()->confirmed_at);
        $this->assertNull($confirmState->fresh()->confirmed_at);
    }

    public function test_a_fixed_tag_can_be_applied_to_a_transaction(): void
    {
        $tx = $this->tx();
        $tag = LedgerTag::where('slug', 'gear')->firstOrFail();

        $this->actingAs($this->createBureauUser())->post(route('admin.ledger.tag', $tx), ['tag_id' => $tag->id])->assertRedirect();

        $this->assertTrue($tx->fresh()->tags->contains('id', $tag->id));
    }

    public function test_a_variable_tag_carries_its_filled_in_value(): void
    {
        $tx = $this->tx();
        $tag = LedgerTag::where('slug', 'for')->firstOrFail();

        $this->actingAs($this->createBureauUser())->post(route('admin.ledger.tag', $tx), ['tag_id' => $tag->id, 'value' => 'Luca G.'])->assertRedirect();

        $pivot = $tx->fresh()->tags->firstWhere('id', $tag->id)->pivot;
        $this->assertSame('Luca G.', $pivot->value);
    }

    public function test_bulk_tag_applies_the_same_tag_to_several_transactions_at_once(): void
    {
        $a = $this->tx();
        $b = $this->tx();
        $tag = LedgerTag::where('slug', 'for')->firstOrFail();

        $this->actingAs($this->createBureauUser())->post(route('admin.ledger.bulk-tag'), [
            'ids' => [$a->id, $b->id], 'tag_id' => $tag->id, 'value' => 'Luca G.',
        ])->assertRedirect();

        $this->assertSame('Luca G.', $a->fresh()->tags->first()->pivot->value);
        $this->assertSame('Luca G.', $b->fresh()->tags->first()->pivot->value);
    }

    public function test_a_suggested_group_can_be_turned_into_a_new_operation_in_one_click(): void
    {
        $tx = $this->tx(['suggested_group' => 'Juan-les-Pins']);

        $this->actingAs($this->createBureauUser())->post(route('admin.ledger.assign-operation', $tx), [
            'new_name' => 'Juan-les-Pins', 'new_kind' => 'trip',
        ])->assertRedirect();

        $tx->refresh();
        $this->assertNotNull($tx->operation);
        $this->assertSame('Juan-les-Pins', $tx->operation->name);
        $this->assertSame('trip', $tx->operation->kind);
    }

    public function test_a_transaction_can_be_assigned_to_an_existing_operation(): void
    {
        $op = LedgerOperation::create(['name' => 'Pool rental', 'kind' => 'cost_centre']);
        $tx = $this->tx();

        $this->actingAs($this->createBureauUser())->post(route('admin.ledger.assign-operation', $tx), ['operation_id' => $op->id])->assertRedirect();

        $this->assertSame($op->id, $tx->fresh()->operation_id);
    }

    public function test_operations_screen_shows_the_net_of_each_operation(): void
    {
        $op = LedgerOperation::create(['name' => 'Todi fine', 'kind' => 'loop']);
        $this->tx(['operation_id' => $op->id, 'amount' => 181.34]);
        $this->tx(['operation_id' => $op->id, 'amount' => -181.34]);

        $this->actingAs($this->createBureauUser())->get(route('admin.ledger.operations'))
            ->assertOk()->assertSee('Todi fine');
    }

    /**
     * Two different imports both number their statements 1, 2, 3... — grouping the
     * balance check by statement_no alone merged unrelated periods (e.g. January of
     * two different years) into one row. Caught on staging importing two real exports.
     */
    public function test_the_statement_balance_check_does_not_merge_statements_with_the_same_number_from_different_files(): void
    {
        $this->tx(['statement_no' => '1', 'source_file' => '2025.xlsx', 'transaction_date' => '2025-01-14']);
        $this->tx(['statement_no' => '1', 'source_file' => '2026.xlsx', 'transaction_date' => '2026-01-05']);

        $this->actingAs($this->createBureauUser())->get(route('admin.ledger.index'))
            ->assertOk()->assertSee('2025.xlsx')->assertSee('2026.xlsx')->assertSee('14/01/2025')->assertSee('05/01/2026');
    }
}
