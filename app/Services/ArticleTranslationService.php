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
        if ($existing) return $existing;

        $title = $this->googleTranslate($article->title, $sourceLocale, $targetLocale);
        $body = $this->googleTranslate($article->body, $sourceLocale, $targetLocale);

        return $article->translations()->create([
            'locale' => $targetLocale,
            'title' => $title ?: $article->title,
            'body' => $body ?: $article->body,
            'auto_translated' => true,
        ]);
    }

    /**
     * Translate all articles to a set of locales.
     */
    public function translateAll(Article $article, array $locales, string $sourceLocale = 'fr'): void
    {
        foreach ($locales as $locale) {
            if ($locale === $sourceLocale) continue;
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
        if (empty(trim(strip_tags($text)))) return $text;

        try {
            $response = Http::get('https://translate.googleapis.com/translate_a/single', [
                'client' => 'gtx',
                'sl' => $from,
                'tl' => $to,
                'dt' => 't',
                'q' => $text,
            ]);

            if (!$response->ok()) return null;

            $data = $response->json();
            $translated = '';
            foreach ($data[0] ?? [] as $segment) {
                $translated .= $segment[0] ?? '';
            }
            return $translated ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
