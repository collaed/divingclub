<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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
        'resend_primary' => 98,
        'resend_secondary' => 98,
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

    /** Get status for dashboard display, including live Mailjet stats. */
    public static function status(): array
    {
        $counts = static::todayCounts();
        $resendQuotas = static::resendQuotas();
        $status = [];

        foreach (self::LIMITS as $provider => $limit) {
            $used = $counts[$provider] ?? 0;

            // Override with live Resend quota if available
            if ($provider === 'resend_primary' && isset($resendQuotas['primary'])) {
                $used = $resendQuotas['primary']['daily'];
                $limit = 100;
            } elseif ($provider === 'resend_secondary' && isset($resendQuotas['secondary'])) {
                $used = $resendQuotas['secondary']['daily'];
                $limit = 100;
            }

            $status[] = [
                'provider' => $provider,
                'used' => $used,
                'limit' => $limit,
                'remaining' => max(0, $limit - $used),
                'pct' => $limit > 0 ? round(min(100, $used / $limit * 100)) : 0,
            ];
        }

        return $status;
    }

    /** Query Resend API for live daily/monthly quotas (cached 5 min). */
    public static function resendQuotas(): array
    {
        return Cache::remember('resend_quotas', 300, function () {
            $quotas = [];

            foreach (['primary' => env('RESEND_KEY'), 'secondary' => env('RESEND_KEY_SECONDARY')] as $label => $key) {
                if (! $key) {
                    continue;
                }
                try {
                    // Send a minimal request to get quota headers
                    // Use the batch endpoint with empty array — returns 400 but still includes headers
                    $ch = curl_init('https://api.resend.com/emails/batch');
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HEADER => true,
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => '[]',
                        CURLOPT_HTTPHEADER => [
                            "Authorization: Bearer {$key}",
                            'Content-Type: application/json',
                        ],
                        CURLOPT_TIMEOUT => 5,
                    ]);
                    $response = curl_exec($ch);
                    curl_close($ch);

                    if (preg_match('/x-resend-daily-quota:\s*(\d+)/i', $response, $d) &&
                        preg_match('/x-resend-monthly-quota:\s*(\d+)/i', $response, $m)) {
                        $quotas[$label] = [
                            'daily' => (int) $d[1],
                            'monthly' => (int) $m[1],
                        ];
                    }
                } catch (\Throwable) {
                    // Skip
                }
            }

            return $quotas;
        });
    }

    /** Total remaining sends across all providers. */
    public static function totalRemaining(): int
    {
        return collect(static::status())->sum('remaining');
    }

    /** Query Mailjet API for this month's usage (cached 15 min). */
    public static function mailjetMonthlyUsage(): ?array
    {
        return Cache::remember('mailjet_monthly_usage', 900, function () {
            $key = env('MAILJET_KEY', env('RESEND_KEY', ''));
            $secret = env('MAILJET_SECRET', '');

            // Use the credentials from config if available
            if (! $key || ! $secret) {
                return null;
            }

            try {
                $from = now()->startOfMonth()->format('Y-m-d\TH:i:s');
                $response = Http::withBasicAuth($key, $secret)
                    ->timeout(5)
                    ->get('https://api.mailjet.com/v3/REST/statcounters', [
                        'CounterSource' => 'APIKey',
                        'CounterResolution' => 'Month',
                        'CounterTiming' => 'Message',
                        'FromTS' => $from,
                        'Limit' => 1,
                    ]);

                if (! $response->ok()) {
                    return null;
                }

                $data = $response->json('Data.0');

                return $data ? [
                    'sent' => $data['MessageSentCount'] ?? 0,
                    'month_limit' => 6000,
                    'remaining' => max(0, 6000 - ($data['MessageSentCount'] ?? 0)),
                    'opened' => $data['MessageOpenedCount'] ?? 0,
                    'blocked' => $data['MessageBlockedCount'] ?? 0,
                ] : null;
            } catch (\Throwable) {
                return null;
            }
        });
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
