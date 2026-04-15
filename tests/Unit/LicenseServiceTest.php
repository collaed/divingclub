<?php

namespace Tests\Unit;

use App\Services\LicenseService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @group p1
 */
class LicenseServiceTest extends TestCase
{
    public function test_verify_rejects_empty_string(): void
    {
        $this->assertFalse(LicenseService::verify(''));
    }

    public function test_verify_rejects_single_part(): void
    {
        $this->assertFalse(LicenseService::verify('onlyonepart'));
    }

    public function test_verify_rejects_three_parts(): void
    {
        $this->assertFalse(LicenseService::verify('a.b.c'));
    }

    public function test_verify_rejects_invalid_base64_payload(): void
    {
        $this->assertFalse(LicenseService::verify('!!!invalid!!!.dGVzdA=='));
    }

    public function test_verify_rejects_invalid_signature(): void
    {
        $payload = base64_encode(json_encode(['domain' => 'test.com', 'max_members' => 500, 'expires' => '2030-01-01']));
        $this->assertFalse(LicenseService::verify($payload.'.'.base64_encode('fake-signature')));
    }

    public function test_free_tier_limit_returns_100(): void
    {
        $method = new ReflectionMethod(LicenseService::class, 'freeTierLimit');
        $this->assertSame(100, $method->invoke(null));
    }

    public function test_integrity_ok_returns_true_when_hashes_empty(): void
    {
        $method = new ReflectionMethod(LicenseService::class, 'integrityOk');
        $this->assertTrue($method->invoke(null));
    }
}
