<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Services\BankReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * parseStatement()'s fast path only understands the simple pasted format
 * (date;amount;communication;counterparty). Real PDF exports look nothing
 * like that — multi-line entries, comma-decimal amounts, a trailing +/-
 * sign — so it falls back to Cloudflare Workers AI to extract the same
 * shape from whatever the bank's export actually looks like.
 */
#[Group('p1')]
class BankStatementParsingTest extends TestCase
{
    use RefreshDatabase;

    /** A real KBC/Luxembourg-style export, reported as failing to parse. */
    private const REAL_WORLD_SAMPLE = <<<'TXT'
        07.07.26 VIREMENT DE LA PART DE EDDY COLLART 07.07.26 999,00 +
        15 Rue Meckenheck L-3321 Berchem
        Banque du donneur d'ordre : KEYTBEBBXXX
        Communication : Rechnung 19274/0-3 Reise 19274
        08.07.26 VIREMENT DE LA PART DE EDDY COLLART 08.07.26 1.000,00 +
        15 Rue Meckenheck L-3321 Berchem
        Banque du donneur d'ordre : KEYTBEBB
        Communication : Rechnung 19274/0-3 kHWreq7rcvUn4R5aoQM0iUY0AWgytYnuMW2
        08.07.26 VIREMENT DE LA PART DE EDDY COLLART 08.07.26 1.001,00 +
        15 Rue Meckenheck L-3321 Berchem
        Banque du donneur d'ordre : KEYTBEBB
        Communication : uSfenoKGqfflkpecpbBjFr33rjNpsewWueY
        10.07.26 ORDRE PERMANENT IBAN LU25 0019 9912 4162 9000 10.07.26 101,00 -
        10.07.26 VIREMENT DE LA PART DE COMMISSION EUROPEENNE 10.07.26 8.433,43 +
        200 RUE DE LA LOI B-1040 BRUXELLES
        Banque du donneur d'ordre : GEBABEBB
        Communication : SALARY JULY 2026 26708410762026
        TXT;

    private function cloudflareRespondingWith(array $body): void
    {
        config(['services.cloudflare.account_id' => 'acc', 'services.cloudflare.api_token' => 'tok']);
        Http::fake(['api.cloudflare.com/*' => Http::response([
            'result' => ['choices' => [['message' => ['content' => json_encode($body)]]]],
        ])]);
    }

    public function test_simple_delimited_paste_never_calls_the_ai(): void
    {
        Http::fake();

        $txs = app(BankReconciliationService::class)->parseStatement(
            "15/03/2026;55.00;cotisation;J DUPONT\n16/03/2026;30.00;cotisation;M MARTIN"
        );

        $this->assertCount(2, $txs);
        Http::assertNothingSent();
    }

    public function test_multiline_real_world_export_finds_nothing_on_the_fast_path_and_falls_back_to_ai(): void
    {
        // Cloudflare's actual live response for this sample: despite being
        // asked for YYYY-MM-DD, it echoes the source statement's own
        // DD.MM.YY format back — the extraction must tolerate that.
        $this->cloudflareRespondingWith([
            ['date' => '07.07.26', 'amount' => 999.00, 'communication' => 'Rechnung 19274/0-3 Reise 19274', 'counterparty' => 'EDDY COLLART'],
            ['date' => '08.07.26', 'amount' => 1000.00, 'communication' => 'Rechnung 19274/0-3 kHWreq7rcvUn4R5aoQM0iUY0AWgytYnuMW2', 'counterparty' => 'EDDY COLLART'],
            ['date' => '08.07.26', 'amount' => 1001.00, 'communication' => 'uSfenoKGqfflkpecpbBjFr33rjNpsewWueY', 'counterparty' => 'EDDY COLLART'],
            ['date' => '10.07.26', 'amount' => 8433.43, 'communication' => 'SALARY JULY 2026 26708410762026', 'counterparty' => 'COMMISSION EUROPEENNE'],
        ]);

        $txs = app(BankReconciliationService::class)->parseStatement(self::REAL_WORLD_SAMPLE);

        $this->assertCount(4, $txs);
        $this->assertDatabaseHas('bank_transactions', ['amount' => 999.00, 'counterparty' => 'EDDY COLLART', 'transaction_date' => '2026-07-07']);
        $this->assertDatabaseHas('bank_transactions', ['amount' => 8433.43, 'counterparty' => 'COMMISSION EUROPEENNE', 'transaction_date' => '2026-07-10']);
        // The outgoing standing order (-101,00) must never surface as a transaction.
        $this->assertDatabaseMissing('bank_transactions', ['amount' => 101.00]);
    }

    public function test_ai_rows_missing_a_date_or_amount_are_skipped(): void
    {
        $this->cloudflareRespondingWith([
            ['date' => '2026-07-07', 'amount' => 999.00, 'communication' => 'ok', 'counterparty' => 'EDDY COLLART'],
            ['date' => null, 'amount' => 50.00, 'communication' => 'no date', 'counterparty' => 'X'],
            ['date' => '2026-07-09', 'amount' => 'not-a-number', 'communication' => 'bad amount', 'counterparty' => 'Y'],
        ]);

        $txs = app(BankReconciliationService::class)->parseStatement(self::REAL_WORLD_SAMPLE);

        $this->assertCount(1, $txs);
        $this->assertSame(999.00, (float) BankTransaction::first()->amount);
    }

    public function test_returns_empty_when_ai_credentials_are_not_configured(): void
    {
        config(['services.cloudflare.account_id' => null, 'services.cloudflare.api_token' => null]);

        $txs = app(BankReconciliationService::class)->parseStatement(self::REAL_WORLD_SAMPLE);

        $this->assertSame([], $txs);
    }

    public function test_returns_empty_gracefully_when_cloudflare_errors(): void
    {
        config(['services.cloudflare.account_id' => 'acc', 'services.cloudflare.api_token' => 'tok']);
        Http::fake(['api.cloudflare.com/*' => Http::response('', 500)]);

        $this->assertSame([], app(BankReconciliationService::class)->parseStatement(self::REAL_WORLD_SAMPLE));
    }
}
