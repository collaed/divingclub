<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LedgerCounterparty;
use App\Models\LedgerOperation;
use App\Models\LedgerTag;
use App\Models\LedgerTransaction;
use App\Models\MemberDetail;
use App\Models\User;
use App\Services\LedgerClassificationService;
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

    /**
     * HTML forms cannot nest — a browser silently drops a nested <form>'s own
     * boundary and submits its controls through the enclosing one instead. Caught
     * live on staging: the whole table was wrapped in the bulk-confirm form, so
     * every per-row tag/confirm/assign click actually submitted bulk-confirm and
     * failed with "The ids field is required." — nothing was ever tagged.
     */
    public function test_no_form_on_the_inbox_page_is_nested_inside_another(): void
    {
        $tx = $this->tx(['counterparty_name' => 'Marie Dupont']);

        $html = $this->actingAs($this->createBureauUser())->get(route('admin.ledger.index'))->getContent();

        $dom = new \DOMDocument;
        @$dom->loadHTML((string) $html);
        foreach ($dom->getElementsByTagName('form') as $form) {
            for ($parent = $form->parentNode; $parent; $parent = $parent->parentNode) {
                $this->assertNotSame('form', $parent->nodeName, 'A <form> is nested inside another <form>.');
            }
        }
    }

    public function test_a_confirmed_transaction_drops_out_of_the_inbox(): void
    {
        $this->tx(['counterparty_name' => 'Already Done', 'confirmed_at' => now()]);

        $this->actingAs($this->createBureauUser())->get(route('admin.ledger.index'))->assertOk()->assertDontSee('Already Done');
    }

    /**
     * "?reviewed=1" is the way back to already-confirmed lines — the inbox itself
     * only ever shows what's still outstanding. See LedgerController::index().
     */
    public function test_reviewed_mode_lists_confirmed_lines_and_hides_unconfirmed_ones(): void
    {
        $this->tx(['counterparty_name' => 'Still Open']);
        $this->tx(['counterparty_name' => 'Already Reviewed', 'confirmed_at' => now(), 'confirmed_by' => $this->createBureauUser()->id]);

        $response = $this->actingAs($this->createBureauUser())->get(route('admin.ledger.index', ['reviewed' => 1]));

        $response->assertOk()->assertSee('Already Reviewed')->assertDontSee('Still Open');
    }

    public function test_unconfirming_a_line_sends_it_back_to_the_inbox(): void
    {
        $bureau = $this->createBureauUser();
        $tx = $this->tx(['confirmed_at' => now(), 'confirmed_by' => $bureau->id]);

        $this->actingAs($bureau)->post(route('admin.ledger.unconfirm', $tx))->assertRedirect();

        $tx->refresh();
        $this->assertNull($tx->confirmed_at);
        $this->assertNull($tx->confirmed_by);
    }

    public function test_unconfirming_via_ajax_returns_json_and_the_row_leaves_the_reviewed_list(): void
    {
        $tx = $this->tx(['confirmed_at' => now()]);

        $this->actingAs($this->createBureauUser())
            ->postJson(route('admin.ledger.unconfirm', $tx))
            ->assertOk()
            ->assertJson(['ok' => true, 'removed' => true, 'id' => $tx->id]);

        $this->assertNull($tx->fresh()->confirmed_at);
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

    /**
     * The in-place AJAX path (.kiro/steering/in-place-ajax.md): an XHR request
     * asking for JSON gets a JSON body it can act on without a page reload,
     * instead of the redirect+flash the plain-form fallback gets.
     */
    public function test_confirming_via_ajax_returns_json_and_the_row_is_gone_from_a_refetch(): void
    {
        $tx = $this->tx();

        $this->actingAs($this->createBureauUser())
            ->postJson(route('admin.ledger.confirm', $tx))
            ->assertOk()
            ->assertJson(['ok' => true, 'removed' => true, 'id' => $tx->id]);

        $this->assertNotNull($tx->fresh()->confirmed_at);
    }

    /**
     * Caught live: a bureau member "confirmed the suggestion" by clicking
     * Confirm alone — the group box's suggested value looked already "set"
     * in the UI, but nothing had actually submitted it. Confirm now folds
     * in whatever's showing there.
     */
    public function test_confirming_with_a_pending_group_name_also_assigns_it(): void
    {
        $tx = $this->tx(['suggested_group' => 'Juan-les-Pins']);

        $this->actingAs($this->createBureauUser())
            ->postJson(route('admin.ledger.confirm', $tx), ['new_name' => 'Juan-les-Pins'])
            ->assertOk()->assertJson(['ok' => true]);

        $tx->refresh();
        $this->assertNotNull($tx->confirmed_at);
        $this->assertTrue($tx->operations->contains('name', 'Juan-les-Pins'));
    }

    public function test_confirming_reuses_an_existing_operation_by_name_rather_than_duplicating(): void
    {
        $existing = LedgerOperation::create(['name' => 'Juan-les-Pins', 'kind' => LedgerOperation::KIND_TRIP]);
        $tx = $this->tx();

        $this->actingAs($this->createBureauUser())->postJson(route('admin.ledger.confirm', $tx), ['new_name' => 'juan-les-pins']);

        $this->assertSame(1, LedgerOperation::where('name', 'Juan-les-Pins')->count());
        $this->assertTrue($tx->fresh()->operations->contains('id', $existing->id));
    }

    public function test_confirming_with_no_group_shown_behaves_exactly_as_before(): void
    {
        $tx = $this->tx();

        $this->actingAs($this->createBureauUser())->postJson(route('admin.ledger.confirm', $tx))->assertOk();

        $this->assertCount(0, $tx->fresh()->operations);
    }

    public function test_bulk_confirming_via_ajax_returns_the_confirmed_ids_and_state_counts(): void
    {
        $expected = $this->tx(['state' => LedgerTransaction::STATE_EXPECTED]);
        $unknown = $this->tx(['state' => LedgerTransaction::STATE_UNKNOWN]);

        $response = $this->actingAs($this->createBureauUser())
            ->postJson(route('admin.ledger.bulk-confirm'), ['ids' => [$expected->id, $unknown->id]])
            ->assertOk()
            ->assertJson(['ok' => true, 'removedIds' => [$expected->id]]);

        $this->assertArrayHasKey('stateCounts', $response->json());
        $this->assertNotNull($expected->fresh()->confirmed_at);
        $this->assertNull($unknown->fresh()->confirmed_at);
    }

    public function test_tagging_via_ajax_returns_the_re_rendered_row_html(): void
    {
        $tx = $this->tx(['counterparty_name' => 'Marie Dupont']);
        $tag = LedgerTag::where('slug', 'cotisation')->firstOrFail();

        $response = $this->actingAs($this->createBureauUser())
            ->postJson(route('admin.ledger.tag', $tx), ['tag_id' => $tag->id])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertStringContainsString('#'.$tag->label, $response->json('html'));
        $this->assertTrue($tx->fresh()->tags->contains('id', $tag->id));
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

    /**
     * Selecting only ineligible rows used to silently do nothing but say
     * "0 line(s) confirmed." — read live as the button being broken. It now
     * says explicitly that they weren't green, and flashes as a warning
     * rather than a success when nothing was actually confirmed.
     */
    public function test_bulk_confirm_explains_when_everything_selected_was_skipped(): void
    {
        $confirmState = $this->tx(['state' => LedgerTransaction::STATE_CONFIRM]);

        $response = $this->actingAs($this->createBureauUser())->from(route('admin.ledger.index'))
            ->post(route('admin.ledger.bulk-confirm'), ['ids' => [$confirmState->id]]);

        $response->assertRedirect();
        $this->assertNull($confirmState->fresh()->confirmed_at);
        $this->assertStringContainsString('not confirmed', session('warning'));
        $this->assertNull(session('success'));
    }

    public function test_bulk_confirm_reports_both_confirmed_and_skipped_when_mixed(): void
    {
        $expected = $this->tx(['state' => LedgerTransaction::STATE_EXPECTED]);
        $unknown = $this->tx(['state' => LedgerTransaction::STATE_UNKNOWN]);

        $this->actingAs($this->createBureauUser())->post(route('admin.ledger.bulk-confirm'), ['ids' => [$expected->id, $unknown->id]]);

        $this->assertStringContainsString('1 line(s) confirmed', session('success'));
        $this->assertStringContainsString('not confirmed', session('success'));
    }

    public function test_the_bulk_select_checkbox_is_disabled_for_lines_that_are_not_green(): void
    {
        $eligible = $this->tx(['state' => LedgerTransaction::STATE_RECOGNISED, 'counterparty_name' => 'Eligible Row']);
        $ineligible = $this->tx(['state' => LedgerTransaction::STATE_CONFIRM, 'counterparty_name' => 'Ineligible Row']);

        $html = $this->actingAs($this->createBureauUser())->get(route('admin.ledger.index'))->getContent();

        $dom = new \DOMDocument;
        @$dom->loadHTML((string) $html);
        $xpath = new \DOMXPath($dom);
        $eligibleBox = $xpath->query("//input[@value='{$eligible->id}']")->item(0);
        $ineligibleBox = $xpath->query("//input[@value='{$ineligible->id}']")->item(0);

        $this->assertFalse($eligibleBox->hasAttribute('disabled'));
        $this->assertTrue($ineligibleBox->hasAttribute('disabled'));
    }

    public function test_a_fixed_tag_can_be_applied_to_a_transaction(): void
    {
        $tx = $this->tx();
        $tag = LedgerTag::where('slug', 'gear')->firstOrFail();

        $this->actingAs($this->createBureauUser())->post(route('admin.ledger.tag', $tx), ['tag_id' => $tag->id])->assertRedirect();

        $this->assertTrue($tx->fresh()->tags->contains('id', $tag->id));
    }

    public function test_a_tag_can_be_removed_from_a_transaction(): void
    {
        $tx = $this->tx();
        $keep = LedgerTag::where('slug', 'gear')->firstOrFail();
        $remove = LedgerTag::where('slug', 'cotisation')->firstOrFail();
        $tx->tags()->attach([$keep->id, $remove->id]);

        $this->actingAs($this->createBureauUser())->delete(route('admin.ledger.tag.remove', [$tx, $remove]))->assertRedirect();

        $tx->refresh();
        $this->assertFalse($tx->tags->contains('id', $remove->id));
        $this->assertTrue($tx->tags->contains('id', $keep->id));
    }

    public function test_removing_a_tag_the_transaction_never_had_is_a_harmless_noop(): void
    {
        $tx = $this->tx();
        $tag = LedgerTag::where('slug', 'gear')->firstOrFail();

        $this->actingAs($this->createBureauUser())->delete(route('admin.ledger.tag.remove', [$tx, $tag]))->assertRedirect();

        $this->assertFalse($tx->fresh()->tags->contains('id', $tag->id));
    }

    public function test_seeded_fixed_tags_carry_the_direction_the_club_actually_expects(): void
    {
        $this->assertSame(LedgerTag::DIRECTION_IN, LedgerTag::where('slug', 'cotisation')->firstOrFail()->direction);
        $this->assertSame(LedgerTag::DIRECTION_IN, LedgerTag::where('slug', 'deposit')->firstOrFail()->direction);
        $this->assertSame(LedgerTag::DIRECTION_OUT, LedgerTag::where('slug', 'gear')->firstOrFail()->direction);
        $this->assertSame(LedgerTag::DIRECTION_OUT, LedgerTag::where('slug', 'federation')->firstOrFail()->direction);
        $this->assertNull(LedgerTag::where('slug', 'fine')->firstOrFail()->direction);
        $this->assertNull(LedgerTag::where('slug', 'for')->firstOrFail()->direction);
    }

    public function test_a_new_tag_can_be_created_with_a_money_direction(): void
    {
        $tx = $this->tx();

        $this->actingAs($this->createBureauUser())->post(route('admin.ledger.tag.create', $tx), [
            'label' => 'Sponsorship received', 'direction' => 'in',
        ])->assertRedirect();

        $tag = LedgerTag::where('label', 'Sponsorship received')->firstOrFail();
        $this->assertSame(LedgerTag::DIRECTION_IN, $tag->direction);
    }

    public function test_a_new_tag_with_no_direction_chosen_is_neutral(): void
    {
        $tx = $this->tx();

        $this->actingAs($this->createBureauUser())->post(route('admin.ledger.tag.create', $tx), ['label' => 'Miscellaneous']);

        $this->assertNull(LedgerTag::where('label', 'Miscellaneous')->firstOrFail()->direction);
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

    public function test_a_brand_new_tag_is_created_and_applied_in_one_step(): void
    {
        $tx = $this->tx();
        $before = LedgerTag::count();

        $this->actingAs($this->createBureauUser())->post(route('admin.ledger.tag.create', $tx), ['label' => 'Réception ouverture'])
            ->assertRedirect();

        $this->assertSame($before + 1, LedgerTag::count());
        $tag = LedgerTag::where('label', 'Réception ouverture')->firstOrFail();
        $this->assertSame(LedgerTag::KIND_FIXED, $tag->kind);
        $this->assertTrue($tx->fresh()->tags->contains('id', $tag->id));
    }

    public function test_creating_the_same_new_tag_label_twice_reuses_it_instead_of_duplicating(): void
    {
        $first = $this->tx();
        $second = $this->tx();
        $bureau = $this->createBureauUser();

        $this->actingAs($bureau)->post(route('admin.ledger.tag.create', $first), ['label' => 'Réception ouverture']);
        $this->actingAs($bureau)->post(route('admin.ledger.tag.create', $second), ['label' => 'réception ouverture']); // different case

        $this->assertSame(1, LedgerTag::where('label', 'Réception ouverture')->count());
        $this->assertSame($first->fresh()->tags->first()->id, $second->fresh()->tags->first()->id);
    }

    public function test_a_new_tag_label_colliding_with_an_existing_slug_still_gets_created(): void
    {
        LedgerTag::create(['slug' => 'gonflage_special', 'label' => 'Existing', 'kind' => LedgerTag::KIND_FIXED]);
        $before = LedgerTag::count();
        $tx = $this->tx();

        // Would both slugify to "gonflage_special" — must not collide on the unique slug column.
        $this->actingAs($this->createBureauUser())->post(route('admin.ledger.tag.create', $tx), ['label' => 'Gonflage special'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($before + 1, LedgerTag::count());
        $this->assertSame('gonflage_special_2', LedgerTag::where('label', 'Gonflage special')->firstOrFail()->slug);
    }

    public function test_a_suggested_group_can_be_turned_into_a_new_operation_in_one_click(): void
    {
        $tx = $this->tx(['suggested_group' => 'Juan-les-Pins']);

        $this->actingAs($this->createBureauUser())->post(route('admin.ledger.assign-operation', $tx), [
            'new_name' => 'Juan-les-Pins', 'new_kind' => 'trip',
        ])->assertRedirect();

        $tx->refresh();
        $this->assertCount(1, $tx->operations);
        $this->assertSame('Juan-les-Pins', $tx->operations->first()->name);
        $this->assertSame('trip', $tx->operations->first()->kind);
    }

    /**
     * Each row with the same suggested group posts "new_name" independently, and the
     * page is a full POST redirect — not reloaded between accepts — so a second row
     * accepting "Cap Vert" before the page refreshes must reuse the operation the
     * first row just created, not spawn a duplicate with the same name. Caught live
     * on staging: several real "Cap Vert" lines each created their own operation.
     */
    public function test_accepting_the_same_suggested_group_twice_reuses_one_operation(): void
    {
        $first = $this->tx(['suggested_group' => 'Cap Vert']);
        $second = $this->tx(['suggested_group' => 'Cap Vert']);
        $bureau = $this->createBureauUser();

        $this->actingAs($bureau)->post(route('admin.ledger.assign-operation', $first), ['new_name' => 'Cap Vert', 'new_kind' => 'trip']);
        $this->actingAs($bureau)->post(route('admin.ledger.assign-operation', $second), ['new_name' => 'Cap Vert', 'new_kind' => 'trip']);

        $this->assertSame(1, LedgerOperation::where('name', 'Cap Vert')->count());
        $this->assertSame($first->fresh()->operations->first()->id, $second->fresh()->operations->first()->id);
    }

    public function test_accepting_a_suggested_group_matches_an_existing_operation_case_insensitively(): void
    {
        $existing = LedgerOperation::create(['name' => 'cap vert', 'kind' => 'trip']);
        $tx = $this->tx(['suggested_group' => 'Cap Vert']);

        $this->actingAs($this->createBureauUser())->post(route('admin.ledger.assign-operation', $tx), ['new_name' => 'Cap Vert', 'new_kind' => 'trip']);

        $this->assertSame([$existing->id], $tx->fresh()->operations->pluck('id')->all());
        $this->assertSame(1, LedgerOperation::count());
    }

    public function test_a_transaction_can_be_assigned_to_an_existing_operation(): void
    {
        $op = LedgerOperation::create(['name' => 'Pool rental', 'kind' => 'cost_centre']);
        $tx = $this->tx();

        $this->actingAs($this->createBureauUser())->post(route('admin.ledger.assign-operation', $tx), ['operation_id' => $op->id])->assertRedirect();

        $this->assertSame([$op->id], $tx->fresh()->operations->pluck('id')->all());
    }

    public function test_a_transaction_can_be_assigned_to_a_second_group_without_losing_the_first(): void
    {
        $first = LedgerOperation::create(['name' => 'Todi', 'kind' => 'trip']);
        $second = LedgerOperation::create(['name' => 'Graviere', 'kind' => 'trip']);
        $tx = $this->tx();
        $bureau = $this->createBureauUser();

        $this->actingAs($bureau)->post(route('admin.ledger.assign-operation', $tx), ['operation_id' => $first->id]);
        $this->actingAs($bureau)->post(route('admin.ledger.assign-operation', $tx), ['operation_id' => $second->id]);

        $this->assertSame([$first->id, $second->id], $tx->fresh()->operations->pluck('id')->sort()->values()->all());
    }

    public function test_a_group_can_be_removed_from_a_transaction_without_touching_others(): void
    {
        $keep = LedgerOperation::create(['name' => 'Todi', 'kind' => 'trip']);
        $remove = LedgerOperation::create(['name' => 'Graviere', 'kind' => 'trip']);
        $tx = $this->tx();
        $tx->operations()->attach([$keep->id, $remove->id]);

        $this->actingAs($this->createBureauUser())->delete(route('admin.ledger.operation.remove', [$tx, $remove]))->assertRedirect();

        $tx->refresh();
        $this->assertSame([$keep->id], $tx->operations->pluck('id')->all());
    }

    public function test_operations_screen_shows_the_net_of_each_operation(): void
    {
        $op = LedgerOperation::create(['name' => 'Todi fine', 'kind' => 'loop']);
        $a = $this->tx(['amount' => 181.34]);
        $b = $this->tx(['amount' => -181.34]);
        $op->transactions()->attach([$a->id, $b->id]);

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

    /**
     * Continuous re-evaluation: an amber/red line is re-checked after every tag,
     * group, or removal — either it turns out to be "good enough" (green), or its
     * explanation is refreshed to say what's now known, even if the colour doesn't
     * change yet.
     */
    public function test_tagging_a_line_that_already_has_a_known_counterparty_turns_it_green(): void
    {
        $counterparty = LedgerCounterparty::create(['name' => 'Steinfort', 'kind' => 'venue']);
        $tx = $this->tx(['state' => LedgerTransaction::STATE_CONFIRM, 'counterparty_id' => $counterparty->id]);
        $tag = LedgerTag::where('slug', 'pool_rental')->firstOrFail();

        $this->actingAs($this->createBureauUser())->post(route('admin.ledger.tag', $tx), ['tag_id' => $tag->id]);

        $tx->refresh();
        $this->assertSame(LedgerTransaction::STATE_RECOGNISED, $tx->state);
        $this->assertStringContainsString('Pool rental', $tx->state_reason);
        $this->assertStringContainsString('Steinfort', $tx->state_reason);
    }

    public function test_tagging_a_line_with_no_counterparty_stays_amber_but_the_reason_updates(): void
    {
        $tx = $this->tx(['state' => LedgerTransaction::STATE_UNKNOWN, 'state_reason' => 'Neither the counterparty nor the purpose is recognised.']);
        $tag = LedgerTag::where('slug', 'gear')->firstOrFail();

        $this->actingAs($this->createBureauUser())->post(route('admin.ledger.tag', $tx), ['tag_id' => $tag->id]);

        $tx->refresh();
        $this->assertSame(LedgerTransaction::STATE_CONFIRM, $tx->state);
        $this->assertStringContainsString('Gear', $tx->state_reason);
        $this->assertStringContainsString('still needs a human check', $tx->state_reason);
    }

    public function test_grouping_both_sides_of_a_balanced_loop_turns_them_green(): void
    {
        $loop = LedgerOperation::create(['name' => 'Todi fine', 'kind' => LedgerOperation::KIND_LOOP]);
        $a = $this->tx(['state' => LedgerTransaction::STATE_UNKNOWN, 'amount' => 181.34]);
        $b = $this->tx(['state' => LedgerTransaction::STATE_UNKNOWN, 'amount' => -181.34]);
        $bureau = $this->createBureauUser();

        $this->actingAs($bureau)->post(route('admin.ledger.assign-operation', $a), ['operation_id' => $loop->id]);
        $this->actingAs($bureau)->post(route('admin.ledger.assign-operation', $b), ['operation_id' => $loop->id]);

        $this->assertSame(LedgerTransaction::STATE_LOOP, $a->fresh()->state);
        $this->assertSame(LedgerTransaction::STATE_LOOP, $b->fresh()->state);
    }

    public function test_removing_the_only_tag_from_a_recognised_line_drops_it_back_to_confirm(): void
    {
        $counterparty = LedgerCounterparty::create(['name' => 'Steinfort', 'kind' => 'venue']);
        $tag = LedgerTag::where('slug', 'pool_rental')->firstOrFail();
        $tx = $this->tx(['state' => LedgerTransaction::STATE_RECOGNISED, 'counterparty_id' => $counterparty->id]);
        $tx->tags()->attach($tag->id);

        $this->actingAs($this->createBureauUser())->delete(route('admin.ledger.tag.remove', [$tx, $tag]));

        $this->assertSame(LedgerTransaction::STATE_CONFIRM, $tx->fresh()->state);
    }

    public function test_reevaluate_never_touches_an_already_confirmed_line(): void
    {
        $tx = $this->tx(['state' => LedgerTransaction::STATE_CONFIRM, 'confirmed_at' => now(), 'confirmed_by' => $this->createBureauUser()->id]);
        $tag = LedgerTag::where('slug', 'gear')->firstOrFail();

        app(LedgerClassificationService::class)->reevaluate($tx->fresh());

        $this->assertSame(LedgerTransaction::STATE_CONFIRM, $tx->fresh()->state);
    }

    public function test_the_review_screen_filters_by_a_selected_tag_and_sums_the_matches(): void
    {
        $tag = LedgerTag::where('slug', 'deposit')->firstOrFail();
        $matching = $this->tx(['amount' => 100]);
        $matching->tags()->attach($tag->id);
        $other = $this->tx(['amount' => -50]);

        $response = $this->actingAs($this->createBureauUser())->get(route('admin.ledger.review', ['tags' => [$tag->id]]));

        $response->assertOk();
        $shown = $response->viewData('transactions');
        $this->assertTrue($shown->contains('id', $matching->id));
        $this->assertFalse($shown->contains('id', $other->id));
        $this->assertEqualsWithDelta(100.0, $response->viewData('totalIn'), 0.001);
        $this->assertEqualsWithDelta(100.0, $response->viewData('net'), 0.001);
    }

    public function test_the_review_screen_filters_by_a_selected_group_and_nets_a_closed_loop_to_zero(): void
    {
        $loop = LedgerOperation::create(['name' => 'Todi fine', 'kind' => LedgerOperation::KIND_LOOP]);
        $a = $this->tx(['amount' => 181.34]);
        $b = $this->tx(['amount' => -181.34]);
        $loop->transactions()->attach([$a->id, $b->id]);
        $unrelated = $this->tx(['amount' => 999]);

        $response = $this->actingAs($this->createBureauUser())->get(route('admin.ledger.review', ['operations' => [$loop->id]]));

        $shown = $response->viewData('transactions');
        $this->assertCount(2, $shown);
        $this->assertFalse($shown->contains('id', $unrelated->id));
        $this->assertEqualsWithDelta(0.0, $response->viewData('net'), 0.001);
    }

    public function test_the_review_screen_with_nothing_selected_shows_no_lines(): void
    {
        $this->tx();

        $response = $this->actingAs($this->createBureauUser())->get(route('admin.ledger.review'));

        $response->assertOk();
        $this->assertCount(0, $response->viewData('transactions'));
    }
}
