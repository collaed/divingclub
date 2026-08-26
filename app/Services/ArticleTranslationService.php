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

        $title = $this->deeplTranslate($article->title, $sourceLocale, $targetLocale);
        $body = $this->deeplTranslate($article->body, $sourceLocale, $targetLocale);

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
     * Translate arbitrary text (used by email system).
     */
    public function translateText(string $text, string $from, string $to): ?string
    {
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

        // Luxembourgish is not supported by DeepL — skip
        if ($to === 'lb') {
            return null;
        }

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
}
