<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads structured fields (holder name, licence number, validity year) off a
 * scanned federation licence card image using Cloudflare Workers AI's vision
 * model — regex-over-OCR-text can't reliably pull a printed name out of a
 * card layout, but a vision model reading the image directly can.
 */
class CloudflareVisionOcrService
{
    private const MODEL = '@cf/meta/llama-3.2-11b-vision-instruct';

    /**
     * @return array{name: ?string, licence_number: ?string, year: ?string}|null null on any failure (missing config, HTTP error, unparsable reply) — the caller sends the scan to manual review in that case.
     */
    public function extractLicenceFields(string $imagePath): ?array
    {
        $accountId = config('services.cloudflare.account_id');
        $apiToken = config('services.cloudflare.api_token');

        if (! $accountId || ! $apiToken) {
            Log::warning('Cloudflare Workers AI credentials not configured');

            return null;
        }

        $bytes = @file_get_contents($imagePath);
        if ($bytes === false) {
            return null;
        }

        $url = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/".self::MODEL;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiToken,
            ])->timeout(30)->post($url, [
                'messages' => [
                    ['role' => 'system', 'content' => 'You read scanned diving federation licence cards. Reply with ONLY a JSON object and nothing else — no markdown fences, no explanation: {"name": string or null, "licence_number": string or null, "year": string or null}. "name" is the licence holder\'s full name exactly as printed. "licence_number" is the licence/member number printed on the card. "year" is the validity or issue year printed on the card.'],
                    ['role' => 'user', 'content' => 'Extract the fields from this licence card.'],
                ],
                'image' => 'data:image/png;base64,'.base64_encode($bytes),
                'max_tokens' => 256,
            ]);

            if (! $response->ok()) {
                Log::warning('Cloudflare Workers AI vision error', ['status' => $response->status(), 'body' => $response->body()]);

                return null;
            }

            $text = $response->json('result.response');

            return is_string($text) ? $this->parseFields($text) : null;
        } catch (\Throwable $e) {
            Log::warning('Cloudflare Workers AI vision request failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array{name: ?string, licence_number: ?string, year: ?string}|null
     */
    private function parseFields(string $text): ?array
    {
        if (! preg_match('/\{.*\}/s', $text, $m)) {
            Log::warning('Cloudflare Workers AI vision reply had no JSON object', ['reply' => $text]);

            return null;
        }

        $data = json_decode($m[0], true);
        if (! is_array($data)) {
            return null;
        }

        $clean = fn (mixed $v): ?string => is_string($v) && trim($v) !== '' ? trim($v) : null;

        return [
            'name' => $clean($data['name'] ?? null),
            'licence_number' => $clean($data['licence_number'] ?? null),
            'year' => $clean($data['year'] ?? null),
        ];
    }
}
