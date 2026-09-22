<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KanbanCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KanbanIngestTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return [
            'source_document_name' => 'CR bureau 03-09-2026.pdf',
            'source_document_folder' => 'Bureau/Comptes-Rendus/2026',
            'source_document_date' => '2026-09-03',
            'actions' => [
                ['title' => 'Contacter la piscine pour le renouvellement', 'responsible' => 'Roger', 'context' => 'Le contrat piscine arrive à échéance en octobre.'],
                ['title' => 'Commander des blocs supplémentaires', 'responsible' => null, 'context' => null],
            ],
        ];
    }

    public function test_a_request_without_the_configured_token_is_refused(): void
    {
        config(['kanban.ingest_token' => 'secret-token']);

        $this->postJson('/api/kanban/cards', $this->payload())->assertStatus(403);
        $this->postJson('/api/kanban/cards', $this->payload(), ['Authorization' => 'Bearer wrong'])->assertStatus(403);

        $this->assertDatabaseCount('kanban_cards', 0);
    }

    public function test_an_empty_configured_token_closes_the_endpoint_entirely(): void
    {
        config(['kanban.ingest_token' => null]);

        $this->postJson('/api/kanban/cards', $this->payload(), ['Authorization' => 'Bearer anything'])->assertStatus(403);
    }

    public function test_a_matching_token_creates_one_card_per_action(): void
    {
        config(['kanban.ingest_token' => 'secret-token']);

        $this->postJson('/api/kanban/cards', $this->payload(), ['Authorization' => 'Bearer secret-token'])
            ->assertOk()->assertJson(['ok' => true, 'created' => 2]);

        $this->assertDatabaseCount('kanban_cards', 2);
        $this->assertDatabaseHas('kanban_cards', [
            'title' => 'Contacter la piscine pour le renouvellement', 'responsible' => 'Roger',
            'status' => KanbanCard::STATUS_TODO, 'source_document_name' => 'CR bureau 03-09-2026.pdf',
        ]);
    }

    public function test_the_action_list_cannot_be_empty(): void
    {
        config(['kanban.ingest_token' => 'secret-token']);
        $payload = $this->payload();
        $payload['actions'] = [];

        $this->postJson('/api/kanban/cards', $payload, ['Authorization' => 'Bearer secret-token'])->assertStatus(422);
    }
}
