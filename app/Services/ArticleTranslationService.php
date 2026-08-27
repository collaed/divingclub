<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleTranslation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ArticleTranslationService
{
    /**
     * DeepL language code mapping.
     * DeepL uses uppercase codes and some differ from our app locales.
     */
    private const DEEPL_LANG_MAP = [
        'en' => 'EN-GB',
        'de' => 'DE',
        'fr' => 'FR',
        'lb' => 'DE', // Luxembourgish not supported — fall back to German
        'pt' => 'PT-PT',
        'it' => 'IT',
        'nl' => 'NL',
        'es' => 'ES',
        'pl' => 'PL',
        'hu' => 'HU',
        'ro' => 'RO',
        'el' => 'EL',
        'et' => 'ET',
        'sk' => 'SK',
        'fi' => 'FI',
    ];

    private const DEEPL_SOURCE_MAP = [
        'fr' => 'FR',
        'en' => 'EN',
        'de' => 'DE',
    ];

    /**
     * Translate an article to the given locale.
     * Tracks source hash and word counts for quality validation.
     */
    public function translate(Article $article, string $targetLocale, string $sourceLocale = 'fr'): ArticleTranslation
    {
        $existing = $article->translations()->where('locale', $targetLocale)->first();
        $sourceHash = self::sourceHash($article);
        $sourceWords = self::wordCount($article->title.' '.$article->body);

        // Skip if translation exists, is not stale, and source hasn't changed
        if ($existing && ! $existing->stale && $existing->source_hash === $sourceHash) {
            return $existing;
        }

        $title = $this->translateText($article->title, $sourceLocale, $targetLocale);
        $body = $this->translateText($article->body, $sourceLocale, $targetLocale);

        // Validate: if API returned null for both, it's a failure
        if (! $title && ! $body) {
            if ($existing) {
                $existing->increment('retries');
            }

            return $existing ?? $article->translations()->create([
                'locale' => $targetLocale,
                'title' => $article->title,
                'body' => $article->body,
                'auto_translated' => false,
                'stale' => true,
                'source_hash' => $sourceHash,
                'source_word_count' => $sourceWords,
                'retries' => 1,
            ]);
        }

        $translatedTitle = $title ?: $article->title;
        $translatedBody = $body ?: $article->body;
        $translatedWords = self::wordCount($translatedTitle.' '.$translatedBody);

        $data = [
            'title' => $translatedTitle,
            'body' => $translatedBody,
            'auto_translated' => true,
            'stale' => false,
            'source_hash' => $sourceHash,
            'source_word_count' => $sourceWords,
            'translated_word_count' => $translatedWords,
            'retries' => 0,
            'flagged_at' => null,
            'flag_reason' => null,
        ];

        if ($existing) {
            $existing->update($data);
            $result = $existing;
        } else {
            $data['locale'] = $targetLocale;
            $result = $article->translations()->create($data);
        }

        // Validate word count ratio — flag if suspicious
        if (! $result->hasPlausibleWordCount()) {
            $result->update([
                'flagged_at' => now(),
                'flag_reason' => "Word count ratio suspicious: {$sourceWords} source → {$translatedWords} translated ({$targetLocale})",
            ]);
        }

        return $result;
    }

    /**
     * Translate all enabled locales for an article.
     */
    public function translateAll(Article $article, array $locales, string $sourceLocale = 'fr'): void
    {
        foreach ($locales as $locale) {
            if ($locale === $sourceLocale) {
                continue;
            }
            $this->translate($article, $locale, $sourceLocale);
        }
    }

    /**
     * Translate arbitrary text — routes to the appropriate provider.
     * DeepL for supported languages, Cloudflare M2M-100 for Luxembourgish.
     */
    public function translateText(string $text, string $from, string $to): ?string
    {
        if ($to === 'lb') {
            return $this->cloudflareTranslate($text, $from, $to);
        }

        return $this->deeplTranslate($text, $from, $to);
    }

    /** Compute a hash of the article source content for change detection. */
    public static function sourceHash(Article $article): string
    {
        return hash('xxh3', $article->title.'|'.$article->body);
    }

    /** Count words in text (strip HTML first). */
    public static function wordCount(string $text): int
    {
        return str_word_count(strip_tags($text));
    }

    /**
     * Mark translations stale if the source article has changed.
     * Called from ArticleController::update().
     */
    public static function markStaleIfChanged(Article $article): int
    {
        $currentHash = self::sourceHash($article);

        return $article->translations()
            ->where('auto_translated', true)
            ->where(function ($q) use ($currentHash): void {
                $q->where('source_hash', '!=', $currentHash)
                    ->orWhereNull('source_hash');
            })
            ->update(['stale' => true]);
    }

    /**
     * Translate text using DeepL API.
     * Handles HTML natively via tag_handling parameter.
     */
    protected function deeplTranslate(string $text, string $from, string $to): ?string
    {
        if (in_array(trim(strip_tags($text)), ['', '0'], true)) {
            return $text;
        }

        $apiKey = config('services.deepl.key');
        if (! $apiKey) {
            Log::warning('DeepL API key not configured');

            return null;
        }

        $targetLang = self::DEEPL_LANG_MAP[$to] ?? strtoupper($to);
        $sourceLang = self::DEEPL_SOURCE_MAP[$from] ?? strtoupper($from);

        // Determine API endpoint (free vs pro key)
        $baseUrl = str_ends_with($apiKey, ':fx')
            ? 'https://api-free.deepl.com/v2/translate'
            : 'https://api.deepl.com/v2/translate';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'DeepL-Auth-Key '.$apiKey,
            ])->asForm()->post($baseUrl, [
                'text' => $text,
                'source_lang' => $sourceLang,
                'target_lang' => $targetLang,
                'tag_handling' => 'html',
                'split_sentences' => 'nonewlines',
            ]);

            if (! $response->ok()) {
                Log::warning('DeepL API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'target' => $to,
                ]);

                return null;
            }

            $data = $response->json();
            $translated = $data['translations'][0]['text'] ?? null;

            return $translated ?: null;
        } catch (\Throwable $e) {
            Log::warning('DeepL translation failed', ['error' => $e->getMessage(), 'target' => $to]);

            return null;
        }
    }

    /**
     * Cloudflare M2M-100 language name mapping.
     * M2M-100 uses full language names, not ISO codes.
     */
    private const CF_LANG_MAP = [
        'en' => 'english',
        'fr' => 'french',
        'de' => 'german',
        'lb' => 'luxembourgish',
        'pt' => 'portuguese',
        'it' => 'italian',
        'nl' => 'dutch',
        'es' => 'spanish',
        'pl' => 'polish',
        'hu' => 'hungarian',
        'ro' => 'romanian',
        'el' => 'greek',
        'et' => 'estonian',
        'sk' => 'slovak',
        'fi' => 'finnish',
    ];

    /**
     * Translate text using Cloudflare Workers AI (M2M-100).
     * Used as a fallback for languages not supported by DeepL (e.g. Luxembourgish).
     * Does not handle HTML natively — strip tags for short texts, pass raw for longer content.
     */
    protected function cloudflareTranslate(string $text, string $from, string $to): ?string
    {
        if (in_array(trim(strip_tags($text)), ['', '0'], true)) {
            return $text;
        }

        $accountId = config('services.cloudflare.account_id');
        $apiToken = config('services.cloudflare.api_token');

        if (! $accountId || ! $apiToken) {
            Log::warning('Cloudflare Workers AI credentials not configured');

            return null;
        }

        $sourceLang = self::CF_LANG_MAP[$from] ?? $from;
        $targetLang = self::CF_LANG_MAP[$to] ?? $to;

        $url = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/@cf/meta/m2m100-1.2b";

        // M2M-100 doesn't handle HTML — translate stripped text for short content,
        // or chunk by HTML blocks for longer content
        $isHtml = $text !== strip_tags($text);

        if ($isHtml) {
            return $this->cloudflareTranslateHtml($text, $sourceLang, $targetLang, $url, $apiToken);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiToken,
            ])->post($url, [
                'text' => $text,
                'source_lang' => $sourceLang,
                'target_lang' => $targetLang,
            ]);

            if (! $response->ok()) {
                Log::warning('Cloudflare AI error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'target' => $to,
                ]);

                return null;
            }

            return $response->json('result.translated_text');
        } catch (\Throwable $e) {
            Log::warning('Cloudflare translation failed', ['error' => $e->getMessage(), 'target' => $to]);

            return null;
        }
    }

    /**
     * Translate HTML content via Cloudflare by extracting text nodes,
     * translating them individually, and reassembling.
     */
    protected function cloudflareTranslateHtml(string $html, string $sourceLang, string $targetLang, string $url, string $apiToken): ?string
    {
        // Split HTML into tags and text segments
        $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $result = '';

        foreach ($parts as $part) {
            // Skip HTML tags
            if (str_starts_with($part, '<')) {
                $result .= $part;

                continue;
            }

            // Skip whitespace-only segments
            if (trim($part) === '') {
                $result .= $part;

                continue;
            }

            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer '.$apiToken,
                ])->post($url, [
                    'text' => $part,
                    'source_lang' => $sourceLang,
                    'target_lang' => $targetLang,
                ]);

                $translated = $response->ok() ? $response->json('result.translated_text') : null;
                $result .= $translated ?? $part;
                usleep(100000); // 100ms between calls to avoid rate limiting
            } catch (\Throwable) {
                $result .= $part;
            }
        }

        return $result;
    }
}
