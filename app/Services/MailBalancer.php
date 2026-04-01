<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Load-balanced email sending across multiple providers.
 *
 * Tracks daily send counts per provider and rotates to stay within limits.
 * Providers: Resend (primary), Resend (secondary), Mailjet (SMTP via Postfix).
 */
class MailBalancer
{
    /** Daily limits per provider. */
    private const LIMITS = [
        'resend_primary' => 90,
        'resend_secondary' => 90,
        'mailjet' => 200,  // 6000/month ≈ 200/day
    ];

    /** Pick the best provider for the next send. Returns the provider key. */
    public static function nextProvider(): string
    {
        $counts = static::todayCounts();

        foreach (self::LIMITS as $provider => $limit) {
            if (($counts[$provider] ?? 0) < $limit) {
                return $provider;
            }
        }

        // All exhausted — use mailjet anyway (most generous)
        return 'mailjet';
    }

    /** Record a send for a provider. */
    public static function recordSend(string $provider): void
    {
        $key = 'mail_balance_'.date('Y-m-d').'_'.$provider;
        Cache::increment($key);
        // Auto-expire at midnight
        Cache::put($key, Cache::get($key, 1), now()->endOfDay());
    }

    /** Get today's send counts per provider. */
    public static function todayCounts(): array
    {
        $counts = [];
        foreach (array_keys(self::LIMITS) as $provider) {
            $counts[$provider] = (int) Cache::get('mail_balance_'.date('Y-m-d').'_'.$provider, 0);
        }

        return $counts;
    }

    /** Get status for dashboard display. */
    public static function status(): array
    {
        $counts = static::todayCounts();
        $status = [];
        foreach (self::LIMITS as $provider => $limit) {
            $used = $counts[$provider] ?? 0;
            $status[] = [
                'provider' => $provider,
                'used' => $used,
                'limit' => $limit,
                'remaining' => max(0, $limit - $used),
                'pct' => $limit > 0 ? round($used / $limit * 100) : 0,
            ];
        }

        return $status;
    }

    /** Total remaining sends across all providers. */
    public static function totalRemaining(): int
    {
        return collect(static::status())->sum('remaining');
    }

    /**
     * Send an email using the best available provider.
     * Switches Resend API key or falls back to Mailjet (local sendmail/Postfix).
     */
    public static function configureForNext(): string
    {
        $provider = static::nextProvider();

        switch ($provider) {
            case 'resend_primary':
                config(['mail.default' => 'resend']);
                config(['services.resend.key' => env('RESEND_KEY')]);
                break;

            case 'resend_secondary':
                config(['mail.default' => 'resend']);
                config(['services.resend.key' => env('RESEND_KEY_SECONDARY')]);
                break;

            case 'mailjet':
                config(['mail.default' => 'sendmail']);
                break;
        }

        return $provider;
    }
}
