<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleTranslation;
use Illuminate\Support\Facades\Http;

class ArticleTranslationService
{
    /**
     * Translate an article to the given locale using Google Translate (free tier).
     * Stores result in article_translations. Returns the translation.
     */
    public function translate(Article $article, string $targetLocale, string $sourceLocale = 'fr'): ArticleTranslation
    {
        $existing = $article->translations()->where('locale', $targetLocale)->first();

        // Skip if translation exists and is not stale
        if ($existing && ! $existing->stale) {
            return $existing;
        }

        $title = $this->googleTranslate($article->title, $sourceLocale, $targetLocale);
        $body = $this->googleTranslate($article->body, $sourceLocale, $targetLocale);

        if ($existing) {
            $existing->update([
                'title' => $title ?: $article->title,
                'body' => $body ?: $article->body,
                'stale' => false,
                'auto_translated' => true,
            ]);

            return $existing;
        }

        return $article->translations()->create([
            'locale' => $targetLocale,
            'title' => $title ?: $article->title,
            'body' => $body ?: $article->body,
            'auto_translated' => true,
            'stale' => false,
        ]);
    }

    /**
     * Translate all articles to a set of locales.
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

    protected function googleTranslate(string $text, string $from, string $to): ?string
    {
        if (empty(trim(strip_tags($text)))) {
            return $text;
        }

        // Escape variable tokens so the translation engine doesn't mangle them
        $placeholders = [];
        $escaped = preg_replace_callback('/\{\{[^}]+\}\}/', function ($m) use (&$placeholders) {
            $key = '⟦TK'.count($placeholders).'⟧';
            $placeholders[$key] = $m[0];

            return $key;
        }, $text);

        // Escape <img>, <video>, <iframe>, <source> tags — not translatable
        $escaped = preg_replace_callback('/<(img|video|iframe|source|hr|br)\b[^>]*\/?>/i', function ($m) use (&$placeholders) {
            $key = '⟦TK'.count($placeholders).'⟧';
            $placeholders[$key] = $m[0];

            return $key;
        }, $escaped);

        try {
            $response = Http::get('https://translate.googleapis.com/translate_a/single', [
                'client' => 'gtx',
                'sl' => $from,
                'tl' => $to,
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

            // Restore tokens
            return str_replace(array_keys($placeholders), array_values($placeholders), $translated);
        } catch (\Throwable) {
            return null;
        }
    }
}
