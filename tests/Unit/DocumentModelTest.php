<?php

namespace Tests\Unit;

use App\Models\Document;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('p1')]
class DocumentModelTest extends TestCase
{
    public function test_is_expired_when_past(): void
    {
        $doc = new Document;
        $doc->expiry_date = Carbon::yesterday();

        $this->assertTrue($doc->isExpired());
    }

    public function test_is_not_expired_when_future(): void
    {
        $doc = new Document;
        $doc->expiry_date = Carbon::tomorrow();

        $this->assertFalse($doc->isExpired());
    }

    public function test_is_not_expired_when_null(): void
    {
        $doc = new Document;
        $doc->expiry_date = null;

        $this->assertFalse($doc->isExpired());
    }

    public function test_days_until_expiry_positive(): void
    {
        $doc = new Document;
        $doc->expiry_date = Carbon::now()->addDays(10)->startOfDay();

        $this->assertSame(10, $doc->daysUntilExpiry());
    }

    public function test_days_until_expiry_negative_when_expired(): void
    {
        $doc = new Document;
        $doc->expiry_date = Carbon::now()->subDays(5)->startOfDay();

        $this->assertSame(-5, $doc->daysUntilExpiry());
    }

    public function test_days_until_expiry_null_when_no_date(): void
    {
        $doc = new Document;
        $doc->expiry_date = null;

        $this->assertNull($doc->daysUntilExpiry());
    }
}
