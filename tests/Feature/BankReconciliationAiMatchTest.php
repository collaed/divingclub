<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\PaymentExpected;
use App\Services\BankReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * aiMatchRemaining() is the second pass, for transactions the rule-based
 * suggestMatches() couldn't resolve — sends the two remaining lists to
 * Cloudflare Workers AI and applies whatever it proposes as a 'suggested'
 * match (never 'confirmed': a bureau member always reviews before that).
 */
#[Group('p1')]
class BankReconciliationAiMatchTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function cloudflareRespondingWith(array $body): void
    {
        config(['services.cloudflare.account_id' => 'acc', 'services.cloudflare.api_token' => 'tok']);
        Http::fake(['api.cloudflare.com/*' => Http::response([
            'result' => ['choices' => [['message' => ['content' => json_encode($body)]]]],
        ])]);
    }

    private function transaction(array $overrides = []): BankTransaction
    {
        return BankTransaction::create(array_merge([
            'transaction_date' => now(), 'amount' => 55.00,
            'communication' => 'virement', 'counterparty' => 'J DUPONT',
            'status' => 'unmatched',
        ], $overrides));
    }

    private function payment(array $overrides = []): PaymentExpected
    {
        $user = $this->createMemberUser();

        return PaymentExpected::create(array_merge([
            'user_id' => $user->id, 'type' => 'cotisation', 'season_year' => '2026',
            'amount_due' => 55.00, 'amount_paid' => 0, 'communication' => 'CEP-2026-'.$user->id,
            'status' => 'pending',
        ], $overrides));
    }

    public function test_applies_a_high_confidence_proposal_as_suggested_not_confirmed(): void
    {
        $tx = $this->transaction();
        $pe = $this->payment();
        $this->cloudflareRespondingWith([
            ['transaction_id' => $tx->id, 'payment_id' => $pe->id, 'confidence' => 85, 'reason' => 'Amount matches, name is close.'],
        ]);

        $matches = app(BankReconciliationService::class)->aiMatchRemaining();

        $this->assertCount(1, $matches);
        $tx->refresh();
        $this->assertSame('suggested', $tx->status);
        $this->assertSame($pe->id, $tx->matched_payment_id);
        $this->assertSame(85, (int) $tx->match_score);
        $this->assertSame('Amount matches, name is close.', $tx->match_reason);
    }

    public function test_ignores_proposals_below_the_confidence_floor(): void
    {
        $tx = $this->transaction();
        $pe = $this->payment();
        $this->cloudflareRespondingWith([
            ['transaction_id' => $tx->id, 'payment_id' => $pe->id, 'confidence' => 25, 'reason' => 'Weak guess.'],
        ]);

        $matches = app(BankReconciliationService::class)->aiMatchRemaining();

        $this->assertCount(0, $matches);
        $this->assertSame('unmatched', $tx->refresh()->status);
    }

    public function test_ignores_a_proposal_referencing_an_id_not_in_the_input(): void
    {
        $tx = $this->transaction();
        $this->payment();
        $this->cloudflareRespondingWith([
            ['transaction_id' => $tx->id, 'payment_id' => 999999, 'confidence' => 90, 'reason' => 'Hallucinated id.'],
        ]);

        $matches = app(BankReconciliationService::class)->aiMatchRemaining();

        $this->assertCount(0, $matches);
        $this->assertSame('unmatched', $tx->refresh()->status);
    }

    public function test_never_matches_the_same_payment_to_two_transactions(): void
    {
        $tx1 = $this->transaction(['communication' => 'a']);
        $tx2 = $this->transaction(['communication' => 'b']);
        $pe = $this->payment();
        $this->cloudflareRespondingWith([
            ['transaction_id' => $tx1->id, 'payment_id' => $pe->id, 'confidence' => 90, 'reason' => 'first'],
            ['transaction_id' => $tx2->id, 'payment_id' => $pe->id, 'confidence' => 95, 'reason' => 'second, same payment'],
        ]);

        $matches = app(BankReconciliationService::class)->aiMatchRemaining();

        $this->assertCount(1, $matches);
        $this->assertSame('suggested', $tx1->refresh()->status);
        $this->assertSame('unmatched', $tx2->refresh()->status);
    }

    public function test_parses_a_response_wrapped_in_a_markdown_code_fence(): void
    {
        $tx = $this->transaction();
        $pe = $this->payment();
        config(['services.cloudflare.account_id' => 'acc', 'services.cloudflare.api_token' => 'tok']);
        Http::fake(['api.cloudflare.com/*' => Http::response([
            'result' => ['choices' => [['message' => ['content' => "```json\n".json_encode([
                ['transaction_id' => $tx->id, 'payment_id' => $pe->id, 'confidence' => 70, 'reason' => 'fenced'],
            ])."\n```"]]]],
        ])]);

        $matches = app(BankReconciliationService::class)->aiMatchRemaining();

        $this->assertCount(1, $matches);
    }

    public function test_returns_empty_without_calling_cloudflare_when_nothing_is_unmatched(): void
    {
        $this->payment();
        Http::fake();

        $matches = app(BankReconciliationService::class)->aiMatchRemaining();

        $this->assertSame([], $matches);
        Http::assertNothingSent();
    }

    public function test_returns_empty_when_credentials_are_not_configured(): void
    {
        config(['services.cloudflare.account_id' => null, 'services.cloudflare.api_token' => null]);
        $this->transaction();
        $this->payment();

        $this->assertSame([], app(BankReconciliationService::class)->aiMatchRemaining());
    }

    public function test_returns_empty_gracefully_when_cloudflare_errors(): void
    {
        $this->transaction();
        $this->payment();
        config(['services.cloudflare.account_id' => 'acc', 'services.cloudflare.api_token' => 'tok']);
        Http::fake(['api.cloudflare.com/*' => Http::response('', 500)]);

        $this->assertSame([], app(BankReconciliationService::class)->aiMatchRemaining());
    }
}
