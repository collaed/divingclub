<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LedgerCounterparty;
use Illuminate\Database\Seeder;

/**
 * The club's recurring non-member counterparties. A member match is
 * auto-created by LedgerClassificationService as it recognises names; these
 * don't have a member behind them, so they need a starting entry — without
 * one, every line from these regulars would sit in "confirm" forever
 * despite the category being obvious. Names are the exact spelling the
 * bank's own export uses (confirmed against real statements — it isn't
 * always consistent, e.g. Steinfort appears both with and without "DE",
 * and some names are cut off at 35 characters), since the match is exact.
 * Idempotent.
 */
class LedgerCounterpartySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'RECETTE COMMUNALE DE STEINFORT', 'kind' => LedgerCounterparty::KIND_VENUE, 'default_category' => '52'],
            ['name' => 'RECETTE COMMUNALE STEINFORT', 'kind' => LedgerCounterparty::KIND_VENUE, 'default_category' => '52'],
            ['name' => 'Lafont Assurances', 'kind' => LedgerCounterparty::KIND_SUPPLIER, 'default_category' => '82'],
            ['name' => 'COMITE REGIONAL GRAND EST FFESSM', 'kind' => LedgerCounterparty::KIND_FEDERATION, 'default_category' => '81'],
            ['name' => 'CIR EST FFESSM', 'kind' => LedgerCounterparty::KIND_FEDERATION, 'default_category' => '81'],
            ['name' => 'FEDERATION LUXEMBOURGEOISE DES ACTI', 'kind' => LedgerCounterparty::KIND_FEDERATION, 'default_category' => '81'],
            ['name' => 'Federation Regionale Graviere du Fo', 'kind' => LedgerCounterparty::KIND_FEDERATION, 'default_category' => '81'],
            ['name' => 'LUXAIR S A', 'kind' => LedgerCounterparty::KIND_SUPPLIER, 'default_category' => '86'],
            ['name' => 'VOYAGES EMILE WEBER SARL', 'kind' => LedgerCounterparty::KIND_SUPPLIER, 'default_category' => '86'],
            ['name' => 'SARL EASY DIVE', 'kind' => LedgerCounterparty::KIND_SUPPLIER, 'default_category' => '86'],
            ['name' => 'FRAIS DE TENUE DE COMPTE - ZEBRA', 'kind' => LedgerCounterparty::KIND_OTHER, 'default_category' => '85'],
        ];

        foreach ($rows as $row) {
            LedgerCounterparty::firstOrCreate(['name' => $row['name']], $row);
        }
    }
}
