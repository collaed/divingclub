<?php

namespace Tests\Unit;

use App\Services\BankReconciliationService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[Group('p1')]
class BankReconciliationServiceTest extends TestCase
{
    private BankReconciliationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BankReconciliationService;
    }

    // --- parseDate ---

    public function test_parse_date_dd_slash_mm_slash_yyyy(): void
    {
        $this->assertSame('2026-03-15', $this->invokeParseDate('15/03/2026'));
    }

    public function test_parse_date_dd_dash_mm_dash_yyyy(): void
    {
        $this->assertSame('2026-01-20', $this->invokeParseDate('20-01-2026'));
    }

    public function test_parse_date_iso_format_passthrough(): void
    {
        $this->assertSame('2026-03-15', $this->invokeParseDate('2026-03-15'));
    }

    public function test_parse_date_empty_returns_today(): void
    {
        $result = $this->invokeParseDate('');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $result);
    }

    // --- normalizeIban ---

    public function test_normalize_iban_removes_spaces_and_uppercases(): void
    {
        $this->assertSame('LU280019400644750000', $this->invokeNormalizeIban('lu28 0019 4006 4475 0000'));
    }

    public function test_normalize_iban_already_clean(): void
    {
        $this->assertSame('LU280019400644750000', $this->invokeNormalizeIban('LU280019400644750000'));
    }

    // --- Helpers ---

    private function invokeParseDate(string $d): string
    {
        $method = new ReflectionMethod(BankReconciliationService::class, 'parseDate');

        return $method->invoke($this->service, $d);
    }

    private function invokeNormalizeIban(string $iban): string
    {
        $method = new ReflectionMethod(BankReconciliationService::class, 'normalizeIban');

        return $method->invoke($this->service, $iban);
    }
}
