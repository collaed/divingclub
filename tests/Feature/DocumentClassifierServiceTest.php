<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Federation;
use App\Services\DocumentClassifierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('p1')]
class DocumentClassifierServiceTest extends TestCase
{
    use RefreshDatabase;

    private function federation(string $acronym = 'FFESSM'): Federation
    {
        return Federation::create(['acronym' => $acronym, 'full_name' => $acronym, 'visibility' => 'active']);
    }

    public function test_a_named_federation_with_no_bank_wording_is_a_licence_scan(): void
    {
        $fed = $this->federation('FFESSM');

        $result = (new DocumentClassifierService)->classify('FEDERATION FRANCAISE FFESSM - LICENCE N° 123456 - Jean Dupont');

        $this->assertSame('licence_scan', $result['type']);
        $this->assertSame($fed->id, $result['federation_id']);
    }

    public function test_bank_wording_with_no_licence_wording_is_a_bank_statement(): void
    {
        $this->federation();

        $result = (new DocumentClassifierService)->classify("RELEVE DE COMPTE\nIBAN LU28 0019 4006 4475 0000\n15/03/2026;55.00;cotisation;J DUPONT");

        $this->assertSame('bank_statement', $result['type']);
        $this->assertNull($result['federation_id']);
    }

    public function test_licence_keyword_without_a_known_federation_name_still_classifies_as_licence(): void
    {
        $result = (new DocumentClassifierService)->classify('CARTE FEDERALE - LICENCE VALIDE 2026');

        $this->assertSame('licence_scan', $result['type']);
        $this->assertNull($result['federation_id']);
    }

    public function test_ambiguous_text_with_both_signals_falls_back_to_ai(): void
    {
        config(['services.cloudflare.account_id' => 'acc', 'services.cloudflare.api_token' => 'tok']);
        Http::fake(['api.cloudflare.com/*' => Http::response([
            'result' => ['choices' => [['message' => ['content' => json_encode(['type' => 'bank_statement', 'federation' => null, 'reason' => 'Looks like a statement despite the licence mention.'])]]]],
        ])]);

        $result = (new DocumentClassifierService)->classify('LICENCE fees appear as a line item. IBAN LU28...');

        $this->assertSame('bank_statement', $result['type']);
        $this->assertSame('Looks like a statement despite the licence mention.', $result['reason']);
    }

    public function test_unclear_text_with_neither_signal_falls_back_to_ai(): void
    {
        config(['services.cloudflare.account_id' => 'acc', 'services.cloudflare.api_token' => 'tok']);
        Http::fake(['api.cloudflare.com/*' => Http::response([
            'result' => ['choices' => [['message' => ['content' => json_encode(['type' => 'licence_scan', 'federation' => 'FFESSM', 'reason' => 'Card layout, no bank markers.'])]]]],
        ])]);
        $fed = $this->federation('FFESSM');

        $result = (new DocumentClassifierService)->classify('Some illegible OCR text with no clear markers at all.');

        $this->assertSame('licence_scan', $result['type']);
        $this->assertSame($fed->id, $result['federation_id']);
    }

    public function test_returns_an_empty_type_when_ai_is_unavailable_and_rules_are_inconclusive(): void
    {
        config(['services.cloudflare.account_id' => null, 'services.cloudflare.api_token' => null]);

        $result = (new DocumentClassifierService)->classify('Nothing distinctive here.');

        $this->assertSame('', $result['type']);
    }

    public function test_returns_an_empty_type_when_ai_returns_an_invalid_type(): void
    {
        config(['services.cloudflare.account_id' => 'acc', 'services.cloudflare.api_token' => 'tok']);
        Http::fake(['api.cloudflare.com/*' => Http::response([
            'result' => ['choices' => [['message' => ['content' => json_encode(['type' => 'something_else'])]]]],
        ])]);

        $result = (new DocumentClassifierService)->classify('Ambiguous.');

        $this->assertSame('', $result['type']);
    }
}
