<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\InboundMailDeduplicator;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class InboundMailDeduplicatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_first_time_message_id_proceeds_then_duplicate_is_skipped(): void
    {
        $id = '<abc-123@mail.example.com>';

        $this->assertTrue(InboundMailDeduplicator::markProcessed($id), 'First occurrence should proceed');
        $this->assertFalse(InboundMailDeduplicator::markProcessed($id), 'Second occurrence should be skipped');
        $this->assertTrue(InboundMailDeduplicator::isProcessed($id));
    }

    public function test_missing_message_id_always_proceeds(): void
    {
        $this->assertTrue(InboundMailDeduplicator::markProcessed(null));
        $this->assertTrue(InboundMailDeduplicator::markProcessed(''));
        $this->assertTrue(InboundMailDeduplicator::markProcessed('   '));
        $this->assertFalse(InboundMailDeduplicator::isProcessed(null));
    }

    public function test_extracts_message_id_from_raw_headers(): void
    {
        $raw = "From: a@b.com\r\nMessage-ID: <xyz-789@host>\r\nSubject: Hi\r\n\r\nbody";

        $this->assertSame('<xyz-789@host>', InboundMailDeduplicator::extractMessageId($raw));
    }

    public function test_returns_null_when_no_message_id_present(): void
    {
        $raw = "From: a@b.com\r\nSubject: Hi\r\n\r\nbody";

        $this->assertNull(InboundMailDeduplicator::extractMessageId($raw));
    }

    public function test_different_ids_are_independent(): void
    {
        $this->assertTrue(InboundMailDeduplicator::markProcessed('<one@host>'));
        $this->assertTrue(InboundMailDeduplicator::markProcessed('<two@host>'));
        $this->assertFalse(InboundMailDeduplicator::markProcessed('<one@host>'));
    }
}
