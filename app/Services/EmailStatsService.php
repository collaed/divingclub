<?php

namespace App\Services;

use App\Models\MemberDetail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Fetches email delivery stats from Mailjet (and optionally Resend) APIs.
 * Cross-references with member database for named reporting.
 */
class EmailStatsService
{
    /**
     * Get delivery stats for a date range, grouped by subject.
     *
     * @return array{subjects: Collection, totals: array}
     */
    public static function forDate(string $date): array
    {
        $messages = array_merge(
            static::fetchMailjetMessages($date),
            static::fetchResendMessages($date),
        );
        $members = static::memberLookup();

        // Group by subject, track best status per recipient
        $subjects = collect();
        foreach ($messages as $msg) {
            $email = mb_strtolower($msg['ContactAlt'] ?? '');
            if (str_starts_with($email, 'clubcep+')) {
                continue; // skip script/alias emails
            }

            $subject = $msg['Subject'] ?? '(no subject)';
            $status = static::normalizeStatus($msg['Status'] ?? '');

            if (! $subjects->has($subject)) {
                $subjects[$subject] = collect();
            }

            $existing = $subjects[$subject]->get($email);
            if (! $existing || static::statusPriority($status) > static::statusPriority($existing['status'])) {
                $member = $members[$email] ?? $members[static::removeDots($email)] ?? null;
                $subjects[$subject][$email] = [
                    'email' => $email,
                    'first_name' => $member['first_name'] ?? '',
                    'last_name' => $member['last_name'] ?? $email,
                    'status' => $status,
                    'arrived_at' => $msg['ArrivedAt'] ?? '',
                ];
            }
        }

        // Sort subjects by count desc, recipients by status then name
        $subjects = $subjects->map(function ($recipients) {
            return $recipients->sortBy([
                fn ($a, $b): int => static::statusPriority($b['status']) <=> static::statusPriority($a['status']),
                fn ($a, $b): int => ($a['last_name'] ?? '') <=> ($b['last_name'] ?? ''),
            ])->values();
        })->sortByDesc(fn ($r) => $r->count());

        // Totals
        $all = $subjects->flatten(1);
        $totals = [
            'messages' => $all->count(),
            'opened' => $all->whereIn('status', ['opened', 'clicked'])->count(),
            'clicked' => $all->where('status', 'clicked')->count(),
            'failed' => $all->where('status', 'failed')->count(),
        ];

        return compact('subjects', 'totals');
    }

    /** Fetch messages from Mailjet for a given date. Cached 5 min. */
    protected static function fetchMailjetMessages(string $date): array
    {
        $key = config('services.mailjet.key');
        $secret = config('services.mailjet.secret');
        if (! $key || ! $secret) {
            return [];
        }

        return Cache::remember("mailjet_msgs_{$date}", 300, function () use ($key, $secret, $date): array {
            $all = [];
            $offset = 0;

            do {
                $response = Http::withBasicAuth($key, $secret)->timeout(10)
                    ->get('https://api.mailjet.com/v3/REST/message', [
                        'FromTS' => strtotime("{$date}T00:00:00"),
                        'ToTS' => strtotime("{$date}T23:59:59"),
                        'Limit' => 1000,
                        'Offset' => $offset,
                        'ShowSubject' => 'true',
                        'ShowContactAlt' => 'true',
                        'Sort' => 'ArrivedAt+DESC',
                    ]);

                if (! $response->ok()) {
                    break;
                }

                $data = $response->json('Data', []);
                $all = array_merge($all, $data);
                $offset += 1000;
            } while (count($data) === 1000);

            return $all;
        });
    }

    /** Fetch messages from Resend for a given date. Cached 5 min. */
    protected static function fetchResendMessages(string $date): array
    {
        $keys = array_filter([config('services.resend.key'), config('services.resend.key_secondary')]);
        if (! $keys) {
            return [];
        }

        return Cache::remember("resend_msgs_{$date}", 300, function () use ($keys, $date): array {
            $all = [];

            foreach ($keys as $key) {
                $response = Http::withToken($key)->timeout(10)
                    ->get('https://api.resend.com/emails');

                if (! $response->ok()) {
                    continue;
                }

                foreach ($response->json('data', []) as $email) {
                    $created = substr($email['created_at'] ?? '', 0, 10);
                    if ($created !== $date) {
                        continue;
                    }

                    $status = match ($email['last_event'] ?? '') {
                        'clicked' => 'clicked',
                        'opened' => 'opened',
                        'delivered', 'sent' => 'sent',
                        default => 'failed',
                    };

                    foreach ($email['to'] ?? [] as $to) {
                        $all[] = [
                            'ContactAlt' => $to,
                            'Subject' => $email['subject'] ?? '(no subject)',
                            'Status' => $status,
                            'ArrivedAt' => $email['created_at'] ?? '',
                        ];
                    }
                }
            }

            return $all;
        });
    }

    /** Build email → member name lookup. */
    protected static function memberLookup(): array
    {
        return Cache::remember('email_stats_members', 3600, function (): array {
            $lookup = [];
            MemberDetail::with('user')->get()->each(function ($d) use (&$lookup): void {
                $email = mb_strtolower($d->user?->primary_email ?? '');
                if ($email) {
                    $lookup[$email] = ['first_name' => $d->first_name, 'last_name' => $d->last_name];
                    $lookup[static::removeDots($email)] = $lookup[$email];
                }
            });

            return $lookup;
        });
    }

    /** Remove dots from local part (Gmail ignores them). */
    protected static function removeDots(string $email): string
    {
        $parts = explode('@', $email, 2);

        return count($parts) === 2
            ? str_replace('.', '', $parts[0]).'@'.$parts[1]
            : $email;
    }

    protected static function normalizeStatus(string $status): string
    {
        return match ($status) {
            'clicked' => 'clicked',
            'opened' => 'opened',
            'sent' => 'sent',
            default => 'failed',
        };
    }

    protected static function statusPriority(string $status): int
    {
        return match ($status) {
            'clicked' => 3,
            'opened' => 2,
            'sent' => 1,
            default => 0,
        };
    }
}
