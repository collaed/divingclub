<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KanbanCard;
use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class KanbanBoardTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private function instructor(): User
    {
        $u = User::factory()->create(['email_verified_at' => now()]);
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'Insty', 'last_name' => 'Ructor']);
        $u->assignRole('instructor');

        return $u;
    }

    private function member(): User
    {
        $u = User::factory()->create(['email_verified_at' => now()]);
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'Plain', 'last_name' => 'Member']);
        $u->assignRole('member');

        return $u;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_bureau_and_instructors_see_the_board_but_a_plain_member_does_not(): void
    {
        KanbanCard::create(['title' => 'Renew the pool contract', 'status' => 'todo', 'source_document_name' => 'CR bureau 01-01-2026.pdf']);

        $this->actingAs($this->createBureauUser())->get(route('kanban.index'))->assertOk()->assertSee('Renew the pool contract');
        $this->actingAs($this->instructor())->get(route('kanban.index'))->assertOk()->assertSee('Renew the pool contract');
        $this->actingAs($this->member())->get(route('kanban.index'))->assertForbidden();
    }

    public function test_cards_are_grouped_by_status_and_discarded_ones_are_hidden(): void
    {
        KanbanCard::create(['title' => 'Todo card', 'status' => 'todo', 'source_document_name' => 'CR 1.pdf']);
        KanbanCard::create(['title' => 'Doing card', 'status' => 'doing', 'source_document_name' => 'CR 2.pdf']);
        KanbanCard::create(['title' => 'Old card', 'status' => 'todo', 'source_document_name' => 'CR 3.pdf', 'discarded_at' => now()]);

        $this->actingAs($this->createBureauUser())->get(route('kanban.index'))
            ->assertOk()->assertSee('Todo card')->assertSee('Doing card')->assertDontSee('Old card');
    }

    public function test_an_instructor_can_move_a_card_between_columns(): void
    {
        $card = KanbanCard::create(['title' => 'Buy tanks', 'status' => 'todo', 'source_document_name' => 'CR 1.pdf']);

        $this->actingAs($this->instructor())->post(route('kanban.status', $card), ['status' => 'doing'])->assertRedirect();

        $this->assertSame('doing', $card->fresh()->status);
    }

    public function test_discarding_a_card_hides_it_without_deleting_it(): void
    {
        $card = KanbanCard::create(['title' => 'Stale action', 'status' => 'todo', 'source_document_name' => 'CR 1.pdf']);

        $this->actingAs($this->createBureauUser())->post(route('kanban.discard', $card))->assertRedirect();

        $this->assertNotNull($card->fresh()->discarded_at);
        $this->assertDatabaseHas('kanban_cards', ['id' => $card->id]);
    }
}
