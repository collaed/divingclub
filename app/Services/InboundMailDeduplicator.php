<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Deduplicates inbound mail by Message-ID.
 *
 * Replicates the legacy mailer's mailIds/ folder: a message whose Message-ID
 * has already been processed within the retention window is skipped, preventing
 * duplicate blasts when a queued job is retried or a Maildir/IMAP message is
 * re-read after a mid-batch failure.
 */
class InboundMailDeduplicator
{
    /** Retention window in seconds (3 days), matching the legacy cleanup. */
    private const TTL_SECONDS = 3 * 24 * 60 * 60;

    /**
     * Register a Message-ID as processed.
     *
     * @return bool true if this is the first time we've seen it (proceed),
     *              false if it was already processed (skip).
     */
    public static function markProcessed(?string $messageId): bool
    {
        // No Message-ID header — cannot dedup, so always proceed.
        if ($messageId === null || trim($messageId) === '') {
            return true;
        }

        $key = static::cacheKey($messageId);

        if (Cache::has($key)) {
            return false;
        }

        Cache::put($key, now()->toIso8601String(), self::TTL_SECONDS);

        return true;
    }

    /** Whether a Message-ID has already been processed. */
    public static function isProcessed(?string $messageId): bool
    {
        if ($messageId === null || trim($messageId) === '') {
            return false;
        }

        return Cache::has(static::cacheKey($messageId));
    }

    /** Extract a Message-ID from a raw header block, if present. */
    public static function extractMessageId(string $rawHeaders): ?string
    {
        if (preg_match('/^Message-ID:\s*(.+)$/im', $rawHeaders, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /** Build a cache-safe key from a Message-ID. */
    protected static function cacheKey(string $messageId): string
    {
        // Normalize surrounding angle brackets so the two inbound entry points
        // (raw pipe vs. parsed Maildir) produce the same key for a message.
        $normalized = trim(trim($messageId), '<>');

        return 'inbound_msgid_'.sha1($normalized);
    }
}
