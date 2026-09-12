<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\ArticleTranslationStaleness;
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

                return $existing;
            }

            // firstOrCreate keyed on (article_id, locale): a concurrent worker
            // may have inserted this translation since the lookup above.
            return $article->translations()->firstOrCreate(
                ['locale' => $targetLocale],
                [
                    'title' => $article->title,
                    'body' => $article->body,
                    'auto_translated' => false,
                    'stale' => true,
                    'source_hash' => $sourceHash,
                    'source_word_count' => $sourceWords,
                    'retries' => 1,
                ]
            );
        }

        // A field that came back null failed to translate and falls back to
        // the French source so the row is never left blank — but that means
        // the translation is only partial. Keep it stale (and count a retry)
        // rather than marking it a clean success, so the next ProcessTranslations
        // sweep picks it back up instead of leaving untranslated text stuck as
        // "done".
        $partial = ! $title || ! $body;
        $translatedTitle = $title ?: $article->title;
        $translatedBody = $body ?: $article->body;
        $translatedWords = self::wordCount($translatedTitle.' '.$translatedBody);

        $data = [
            'title' => $translatedTitle,
            'body' => $translatedBody,
            'auto_translated' => true,
            'stale' => $partial,
            'source_hash' => $sourceHash,
            'source_word_count' => $sourceWords,
            'translated_word_count' => $translatedWords,
            'retries' => $partial ? (($existing?->retries ?? 0) + 1) : 0,
            'flagged_at' => null,
            'flag_reason' => null,
        ];

        // updateOrCreate keyed on (article_id, locale) — tolerates a concurrent
        // worker having created the row since the lookup above (several Horizon
        // workers, or a TranslateArticle job, can process one article at once).
        $result = $article->translations()->updateOrCreate(['locale' => $targetLocale], $data);

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
            // One failing locale (a provider hiccup) must not abort the rest —
            // the gap-fill pass in ProcessTranslations retries it next run.
            try {
                $this->translate($article, $locale, $sourceLocale);
            } catch (\Throwable $e) {
                Log::warning("translateAll: {$locale} failed for '{$article->title}'", ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Translate arbitrary text — routes to the appropriate provider.
     * Cloudflare M2M-100 primary (daily quota resets), DeepL as fallback (lifetime quota).
     */
    public function translateText(string $text, string $from, string $to): ?string
    {
        // Try Cloudflare first (free daily quota resets)
        $result = $this->cloudflareTranslate($text, $from, $to);

        // Fall back to DeepL if Cloudflare failed (not for lb — DeepL doesn't support it)
        if ($result === null && $to !== 'lb') {
            $result = $this->deeplTranslate($text, $from, $to);
        }

        return $result;
    }

    /** Compute a hash of the article source content for change detection. */
    public static function sourceHash(Article $article): string
    {
        return ArticleTranslationStaleness::sourceHash($article);
    }

    /** Count words in text (strip HTML first). */
    public static function wordCount(string $text): int
    {
        return str_word_count(strip_tags($text));
    }

    /**
     * Mark translations stale if the source article has changed.
     *
     * Also runs automatically from a model event on every Article save (see
     * Article::booted()) — this explicit call from ArticleController::update()
     * is now redundant but harmless (the query is idempotent), kept so the
     * controller's intent stays obvious at the call site.
     */
    public static function markStaleIfChanged(Article $article): int
    {
        return ArticleTranslationStaleness::markIfChanged($article);
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
     *
     * A segment that fails to translate falls back to its original (source
     * language) text so the reassembled HTML stays well-formed — but if
     * every segment fails (rate limit, outage), the whole call must report
     * failure rather than silently return the untranslated body as if it
     * had succeeded: the caller only retries/logs when this returns null.
     */
    protected function cloudflareTranslateHtml(string $html, string $sourceLang, string $targetLang, string $url, string $apiToken): ?string
    {
        // Split HTML into tags and text segments
        $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $result = '';
        $segmentsToTranslate = 0;
        $segmentsTranslated = 0;

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

            $segmentsToTranslate++;

            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer '.$apiToken,
                ])->post($url, [
                    'text' => $part,
                    'source_lang' => $sourceLang,
                    'target_lang' => $targetLang,
                ]);

                $translated = $response->ok() ? $response->json('result.translated_text') : null;
                if ($translated) {
                    $segmentsTranslated++;
                } else {
                    Log::warning('Cloudflare AI error (HTML segment)', ['status' => $response->status(), 'target' => $targetLang]);
                }
                $result .= $translated ?? $part;
                usleep(100000); // 100ms between calls to avoid rate limiting
            } catch (\Throwable $e) {
                Log::warning('Cloudflare translation failed (HTML segment)', ['error' => $e->getMessage(), 'target' => $targetLang]);
                $result .= $part;
            }
        }

        // Every segment failed (e.g. rate-limited for the whole request) —
        // report total failure instead of the untranslated original.
        if ($segmentsToTranslate > 0 && $segmentsTranslated === 0) {
            return null;
        }

        return $result;
    }
}
