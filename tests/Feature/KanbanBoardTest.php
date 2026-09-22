<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KanbanCard;
use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * The board first went to production restricted to bureau_master only,
 * matching the other "super high privilege" items in the nav (e.g. Votes).
 * Opened up to every bureau role once it proved useful — still not
 * instructors or members, who never had access.
 */
class KanbanBoardTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private function withRole(string $role): User
    {
        $u = User::factory()->create(['email_verified_at' => now()]);
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'Test', 'last_name' => ucfirst($role)]);
        $u->assignRole($role);

        return $u;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_any_bureau_role_sees_the_board_but_not_instructors_or_members(): void
    {
        KanbanCard::create(['title' => 'Renew the pool contract', 'status' => 'todo', 'source_document_name' => 'CR bureau 01-01-2026.pdf']);

        $this->actingAs($this->createBureauUser())->get(route('kanban.index'))->assertOk()->assertSee('Renew the pool contract');

        foreach (['bureau_finance', 'bureau_technical'] as $role) {
            $this->actingAs($this->withRole($role))->get(route('kanban.index'))->assertOk()->assertSee('Renew the pool contract');
        }

        foreach (['instructor', 'instructor_apnea', 'member'] as $role) {
            $this->actingAs($this->withRole($role))->get(route('kanban.index'))->assertForbidden();
        }
    }

    public function test_cards_are_grouped_by_status_and_discarded_ones_are_hidden(): void
    {
        KanbanCard::create(['title' => 'Todo card', 'status' => 'todo', 'source_document_name' => 'CR 1.pdf']);
        KanbanCard::create(['title' => 'Doing card', 'status' => 'doing', 'source_document_name' => 'CR 2.pdf']);
        KanbanCard::create(['title' => 'Old card', 'status' => 'todo', 'source_document_name' => 'CR 3.pdf', 'discarded_at' => now()]);

        $this->actingAs($this->createBureauUser())->get(route('kanban.index'))
            ->assertOk()->assertSee('Todo card')->assertSee('Doing card')->assertDontSee('Old card');
    }

    public function test_bureau_master_can_move_a_card_between_columns(): void
    {
        $card = KanbanCard::create(['title' => 'Buy tanks', 'status' => 'todo', 'source_document_name' => 'CR 1.pdf']);

        $this->actingAs($this->createBureauUser())->post(route('kanban.status', $card), ['status' => 'doing'])->assertRedirect();

        $this->assertSame('doing', $card->fresh()->status);
    }

    public function test_bureau_finance_can_also_move_a_card(): void
    {
        $card = KanbanCard::create(['title' => 'Buy tanks', 'status' => 'todo', 'source_document_name' => 'CR 1.pdf']);

        $this->actingAs($this->withRole('bureau_finance'))->post(route('kanban.status', $card), ['status' => 'doing'])->assertRedirect();

        $this->assertSame('doing', $card->fresh()->status);
    }

    public function test_an_instructor_can_no_longer_move_a_card(): void
    {
        $card = KanbanCard::create(['title' => 'Buy tanks', 'status' => 'todo', 'source_document_name' => 'CR 1.pdf']);

        $this->actingAs($this->withRole('instructor'))->post(route('kanban.status', $card), ['status' => 'doing'])->assertForbidden();

        $this->assertSame('todo', $card->fresh()->status);
    }

    public function test_a_bureau_member_can_add_a_card_by_hand(): void
    {
        $response = $this->actingAs($this->createBureauUser())->post(route('kanban.store'), [
            'title' => 'Book the venue for the AG',
            'responsible' => 'Fred',
            'context' => 'Discussed but no compte-rendu yet.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('kanban_cards', [
            'title' => 'Book the venue for the AG',
            'responsible' => 'Fred',
            'status' => KanbanCard::STATUS_TODO,
            'source_document_name' => null,
        ]);
    }

    public function test_adding_a_card_requires_a_title(): void
    {
        $this->actingAs($this->createBureauUser())->post(route('kanban.store'), ['title' => ''])
            ->assertSessionHasErrors('title');

        $this->assertDatabaseCount('kanban_cards', 0);
    }

    public function test_a_manually_added_card_is_distinguished_from_an_extracted_one(): void
    {
        $manual = KanbanCard::create(['title' => 'Manual', 'status' => 'todo', 'source_document_name' => null]);
        $extracted = KanbanCard::create(['title' => 'Extracted', 'status' => 'todo', 'source_document_name' => 'CR 1.pdf']);

        $this->assertTrue($manual->isManual());
        $this->assertFalse($extracted->isManual());
    }

    public function test_responsible_color_is_stable_for_the_same_name_and_null_for_no_one(): void
    {
        $a = KanbanCard::create(['title' => 'A', 'status' => 'todo', 'source_document_name' => 'CR 1.pdf', 'responsible' => 'Roger']);
        $b = KanbanCard::create(['title' => 'B', 'status' => 'todo', 'source_document_name' => 'CR 2.pdf', 'responsible' => 'Roger']);
        $unassigned = KanbanCard::create(['title' => 'C', 'status' => 'todo', 'source_document_name' => 'CR 3.pdf']);

        $this->assertNotNull($a->responsibleColor());
        $this->assertSame($a->responsibleColor(), $b->responsibleColor());
        $this->assertNull($unassigned->responsibleColor());
    }

    public function test_a_bureau_member_can_add_a_progress_comment(): void
    {
        $bureau = $this->createBureauUser(); // first_name Admin, last_name Test -> "AT"
        $card = KanbanCard::create(['title' => 'Buy tanks', 'status' => 'todo', 'source_document_name' => 'CR 1.pdf']);

        $this->actingAs($bureau)->post(route('kanban.comments.store', $card), ['body' => 'Quote requested from Nautica.'])
            ->assertRedirect();

        $this->assertDatabaseHas('kanban_card_comments', [
            'kanban_card_id' => $card->id, 'user_id' => $bureau->id, 'body' => 'Quote requested from Nautica.',
        ]);

        $response = $this->actingAs($bureau)->get(route('kanban.index'));
        $response->assertOk()->assertSee('Quote requested from Nautica.')->assertSee('AT');
    }

    public function test_comments_show_oldest_first_to_read_as_a_progress_log(): void
    {
        $bureau = $this->createBureauUser();
        $card = KanbanCard::create(['title' => 'Buy tanks', 'status' => 'todo', 'source_document_name' => 'CR 1.pdf']);
        $card->comments()->create(['user_id' => $bureau->id, 'body' => 'First step done.']);
        $card->comments()->create(['user_id' => $bureau->id, 'body' => 'Second step done.']);

        $bodies = $card->fresh()->comments->pluck('body')->all();

        $this->assertSame(['First step done.', 'Second step done.'], $bodies);
    }

    public function test_adding_a_comment_requires_a_body(): void
    {
        $card = KanbanCard::create(['title' => 'Buy tanks', 'status' => 'todo', 'source_document_name' => 'CR 1.pdf']);

        $this->actingAs($this->createBureauUser())->post(route('kanban.comments.store', $card), ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('kanban_card_comments', 0);
    }

    public function test_an_instructor_cannot_comment_on_a_card(): void
    {
        $card = KanbanCard::create(['title' => 'Buy tanks', 'status' => 'todo', 'source_document_name' => 'CR 1.pdf']);

        $this->actingAs($this->withRole('instructor'))->post(route('kanban.comments.store', $card), ['body' => 'x'])
            ->assertForbidden();

        $this->assertDatabaseCount('kanban_card_comments', 0);
    }

    public function test_discarding_a_card_hides_it_without_deleting_it(): void
    {
        $card = KanbanCard::create(['title' => 'Stale action', 'status' => 'todo', 'source_document_name' => 'CR 1.pdf']);

        $this->actingAs($this->createBureauUser())->post(route('kanban.discard', $card))->assertRedirect();

        $this->assertNotNull($card->fresh()->discarded_at);
        $this->assertDatabaseHas('kanban_cards', ['id' => $card->id]);
    }
}
