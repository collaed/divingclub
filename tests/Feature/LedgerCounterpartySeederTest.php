<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LedgerCounterparty;
use Database\Seeders\LedgerCounterpartySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('p1')]
class LedgerCounterpartySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_idempotent_and_seeds_the_known_regulars(): void
    {
        $this->seed(LedgerCounterpartySeeder::class);
        $this->seed(LedgerCounterpartySeeder::class);

        $this->assertSame(11, LedgerCounterparty::count());
        $this->assertDatabaseHas('ledger_counterparties', ['name' => 'Lafont Assurances', 'default_category' => '82']);
        $this->assertDatabaseHas('ledger_counterparties', ['name' => 'FRAIS DE TENUE DE COMPTE - ZEBRA', 'default_category' => '85']);
    }
}
