<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Federation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Classifies an uploaded document as a federation licence scan or a bank
 * statement, from its extracted text — so a bureau member can drop either
 * kind on one shared "Document Intake" screen instead of remembering which
 * of two separate screens to use.
 *
 * Keyword rules first (cheap, and correct almost always — the two document
 * types don't actually look alike): a bank statement has IBAN-shaped
 * strings and statement vocabulary, a licence card names its federation
 * and says "licence" somewhere. Only asks Cloudflare Workers AI when
 * neither ruleset is confident, since that's the expensive path.
 */
class DocumentClassifierService
{
    private const BANK_KEYWORDS = ['RELEVÉ DE COMPTE', 'RELEVE DE COMPTE', 'EXTRAIT DE COMPTE', 'BANK STATEMENT', 'KONTOAUSZUG', 'IBAN', 'BIC'];

    private const LICENCE_KEYWORDS = ['LICENCE', 'LIZENZ', 'CARTE FÉDÉRALE', 'CARTE FEDERALE', 'N° DE LICENCE', 'NUMERO DE LICENCE'];

    /** @return array{type: string, federation_id: int|null, reason: string} */
    public function classify(string $text): array
    {
        $upper = mb_strtoupper($text);

        $federation = Federation::query()
            ->get()
            ->first(fn (Federation $f): bool => $f->acronym && str_contains($upper, mb_strtoupper($f->acronym)));

        $bankHits = collect(self::BANK_KEYWORDS)->filter(fn (string $k): bool => str_contains($upper, $k));
        $licenceHits = collect(self::LICENCE_KEYWORDS)->filter(fn (string $k): bool => str_contains($upper, $k));

        // A named federation is the strongest possible licence signal —
        // a bank statement has no reason to mention FFESSM or FLASSA.
        if ($federation && $bankHits->isEmpty()) {
            return ['type' => 'licence_scan', 'federation_id' => $federation->id, 'reason' => "Mentions federation \"{$federation->acronym}\"."];
        }

        if ($bankHits->isNotEmpty() && $licenceHits->isEmpty()) {
            return ['type' => 'bank_statement', 'federation_id' => null, 'reason' => 'Contains bank-statement wording: '.$bankHits->implode(', ').'.'];
        }

        if ($licenceHits->isNotEmpty() && $bankHits->isEmpty()) {
            return ['type' => 'licence_scan', 'federation_id' => $federation?->id, 'reason' => 'Contains licence wording: '.$licenceHits->implode(', ').'.'];
        }

        // Neither ruleset was confident (both matched, or neither did) —
        // worth the extra call to ask a model that can actually read the text.
        return $this->askCloudflareToClassify($text) ?? ['type' => '', 'federation_id' => null, 'reason' => 'Could not determine the document type automatically.'];
    }

    /** @return array{type: string, federation_id: int|null, reason: string}|null */
    private function askCloudflareToClassify(string $text): ?array
    {
        $accountId = config('services.cloudflare.account_id');
        $apiToken = config('services.cloudflare.api_token');
        if (! $accountId || ! $apiToken) {
            return null;
        }

        $federations = Federation::pluck('acronym')->filter()->values();
        $prompt = 'This is text extracted from a scanned or digital document for a diving club\'s admin system.'
            .' Decide whether it is (a) a diving-federation membership licence card/certificate for one specific person, or (b) a bank account statement listing multiple transactions.'
            .' Known federations: '.$federations->implode(', ').'.'
            ."\n\nDocument text (truncated):\n".mb_substr($text, 0, 3000)
            ."\n\nRespond with ONLY this JSON object, no other text:\n"
            .'{"type": "licence_scan" or "bank_statement", "federation": "<acronym or null>", "reason": "<one short sentence>"}';

        try {
            $response = Http::withToken($apiToken)->timeout(30)->post(
                "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/@cf/meta/llama-3.1-8b-instruct",
                ['messages' => [['role' => 'user', 'content' => $prompt]]]
            );

            if (! $response->ok()) {
                Log::warning('Cloudflare AI document classification failed', ['status' => $response->status()]);

                return null;
            }

            $content = $response->json('result.choices.0.message.content');
            if (! is_string($content) || $content === '') {
                return null;
            }

            $content = trim((string) preg_replace('/^```(?:json)?|```$/m', '', trim($content)));
            $decoded = json_decode($content, true);
            if (! is_array($decoded) || ! in_array($decoded['type'] ?? null, ['licence_scan', 'bank_statement'], true)) {
                return null;
            }

            $federation = isset($decoded['federation'])
                ? Federation::whereRaw('UPPER(acronym) = ?', [mb_strtoupper((string) $decoded['federation'])])->first()
                : null;

            return [
                'type' => $decoded['type'],
                'federation_id' => $federation?->id,
                'reason' => is_string($decoded['reason'] ?? null) ? $decoded['reason'] : 'Classified by AI.',
            ];
        } catch (\Throwable $e) {
            Log::warning('Cloudflare AI document classification failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
