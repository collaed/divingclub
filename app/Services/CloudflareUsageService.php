<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pulls Workers AI neuron/request usage per day from Cloudflare's GraphQL
 * Analytics API (the aiInferenceAdaptiveGroups dataset — confirmed against
 * the account's live schema; per-request neuron usage isn't returned by the
 * /ai/run/ responses themselves, only via this account-level dataset).
 *
 * Requires the configured API token to carry the "Account Analytics: Read"
 * permission — the token this app already uses for Workers AI inference
 * calls does not have it by default, so this returns an empty array (and
 * logs a warning) until a suitably-scoped token is configured.
 */
class CloudflareUsageService
{
    private const QUERY = <<<'GQL'
        query ($accountTag: String, $since: Date, $until: Date) {
          viewer {
            accounts(filter: {accountTag: $accountTag}) {
              aiInferenceAdaptiveGroups(
                limit: 1000
                filter: {date_geq: $since, date_leq: $until}
                orderBy: [date_ASC]
              ) {
                count
                dimensions { date modelId }
                sum { totalNeurons }
              }
            }
          }
        }
        GQL;

    /**
     * @return list<array{date: string, model_id: string, neurons: int, requests: int}>
     */
    public function fetchDailyUsage(\DateTimeInterface $since, \DateTimeInterface $until): array
    {
        $accountId = config('services.cloudflare.account_id');
        $token = config('services.cloudflare.api_token');

        if (! $accountId || ! $token) {
            return [];
        }

        try {
            $response = Http::withToken($token)->timeout(15)->post('https://api.cloudflare.com/client/v4/graphql', [
                'query' => self::QUERY,
                'variables' => [
                    'accountTag' => $accountId,
                    'since' => $since->format('Y-m-d'),
                    'until' => $until->format('Y-m-d'),
                ],
            ]);

            if (! $response->ok() || $response->json('errors')) {
                Log::warning('Cloudflare Analytics API error', ['status' => $response->status(), 'body' => $response->body()]);

                return [];
            }

            $groups = $response->json('data.viewer.accounts.0.aiInferenceAdaptiveGroups') ?? [];

            return collect($groups)->map(fn (array $g): array => [
                'date' => $g['dimensions']['date'],
                'model_id' => $g['dimensions']['modelId'],
                'neurons' => (int) ($g['sum']['totalNeurons'] ?? 0),
                'requests' => (int) ($g['count'] ?? 0),
            ])->all();
        } catch (\Throwable $e) {
            Log::warning('Cloudflare Analytics API request failed', ['error' => $e->getMessage()]);

            return [];
        }
    }
}
