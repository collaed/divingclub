<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleTranslation;
use Illuminate\Support\Facades\Http;

class ArticleTranslationService
{
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

        $title = $this->googleTranslate($article->title, $sourceLocale, $targetLocale);
        $body = $this->googleTranslate($article->body, $sourceLocale, $targetLocale);

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
        return $this->googleTranslate($text, $from, $to);
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

    protected function googleTranslate(string $text, string $from, string $to): ?string
    {
        // Chunk long texts to stay under Google's ~5000 char limit
        if (mb_strlen($text) > 4500) {
            return $this->googleTranslateChunked($text, $from, $to);
        }
        if (in_array(trim(strip_tags($text)), ['', '0'], true)) {
            return $text;
        }

        $placeholders = [];
        $escaped = preg_replace_callback('/\{\{[^}]+\}\}/', function ($m) use (&$placeholders): string {
            $key = '⟦TK'.count($placeholders).'⟧';
            $placeholders[$key] = $m[0];

            return $key;
        }, $text);

        $escaped = preg_replace_callback('/<(img|video|iframe|source|hr|br)\b[^>]*\/?>/i', function ($m) use (&$placeholders): string {
            $key = '⟦TK'.count($placeholders).'⟧';
            $placeholders[$key] = $m[0];

            return $key;
        }, $escaped);

        try {
            $response = Http::get('https://translate.googleapis.com/translate_a/single', [
                'client' => 'gtx',
                'sl' => $from,
                'tl' => $to === 'pt' ? 'pt-PT' : $to,
                'dt' => 't',
                'q' => $escaped,
            ]);

            if (! $response->ok()) {
                return null;
            }

            $data = $response->json();
            $translated = '';
            foreach ($data[0] ?? [] as $segment) {
                $translated .= $segment[0] ?? '';
            }

            if (! $translated) {
                return null;
            }

            return str_replace(array_keys($placeholders), array_values($placeholders), $translated);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function googleTranslateChunked(string $text, string $from, string $to): ?string
    {
        // Split on paragraph boundaries
        $parts = preg_split('/(<\/p>|<\/h[1-6]>|<br\s*\/?>)/i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $chunks = [];
        $current = '';

        foreach ($parts as $part) {
            if (mb_strlen($current.$part) > 4000 && $current !== '') {
                $chunks[] = $current;
                $current = $part;
            } else {
                $current .= $part;
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        $translated = '';
        foreach ($chunks as $chunk) {
            $result = $this->googleTranslate($chunk, $from, $to);
            if ($result === null) {
                return null;
            }
            $translated .= $result;
            usleep(300000); // 300ms between chunks
        }

        return $translated;
    }
}
