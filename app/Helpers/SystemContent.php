<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Article;
use App\Models\User;

/**
 * Editable, translatable content blocks that live in the normal Article CMS but
 * are addressed by a STABLE slug (no random suffix) so application code can
 * embed them. The bureau edits them like any other article; the body and its
 * per-locale translations flow through the existing Article translation
 * pipeline. Examples: the dues-page footer, the home landing article.
 */
class SystemContent
{
    /**
     * Slug used for the editable footer shown at the bottom of the dues page.
     */
    public const DUES_FOOTER = 'sys-dues-footer';

    /**
     * Slug used for the editable public landing (home3) article.
     */
    public const HOME_LANDING = 'sys-home-landing';

    /**
     * Fetch a system article by its stable slug, or null when it does not exist.
     */
    public static function article(string $slug): ?Article
    {
        return Article::withTrashed()->where('slug', $slug)->first();
    }

    /**
     * The rendered (sanitized, locale-resolved) HTML body of a system article,
     * or an empty string when the article is missing or empty. Falls back to the
     * original body when no translation exists for the active locale.
     */
    public static function body(string $slug, ?string $locale = null): string
    {
        $article = self::article($slug);
        if (! $article instanceof Article) {
            return '';
        }

        $translated = $article->translated($locale);
        $body = $translated['body'] ?? $article->body;

        if (! is_string($body) || trim($body) === '') {
            return '';
        }

        return HtmlSanitizer::clean($body);
    }

    /**
     * The locale-resolved title of a system article, or the provided default.
     */
    public static function title(string $slug, string $default = ''): string
    {
        $article = self::article($slug);
        if (! $article instanceof Article) {
            return $default;
        }

        $translated = $article->translated($locale = null);

        return is_string($translated['title'] ?? null) && $translated['title'] !== ''
            ? $translated['title']
            : $default;
    }

    /**
     * Ensure a system article exists for the given slug, creating an empty
     * published shell if needed, and return it. Used so the bureau always has a
     * row to edit from the article admin. The created article is public + typed
     * so it is visible/editable through the normal CMS.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function ensure(string $slug, string $title, string $body = '', array $attributes = []): Article
    {
        $existing = self::article($slug);
        if ($existing instanceof Article) {
            return $existing;
        }

        return Article::create(array_merge([
            'title' => $title,
            'slug' => $slug,
            'body' => HtmlSanitizer::clean($body),
            'article_type' => 'news',
            'is_published' => true,
            'is_public' => true,
            'author_id' => self::defaultAuthorId(),
        ], $attributes));
    }

    /**
     * Resolve an author id for auto-created system articles: the current user
     * when available, otherwise a bureau_master, otherwise the first user.
     */
    private static function defaultAuthorId(): ?int
    {
        $current = auth()->id();
        if (is_int($current)) {
            return $current;
        }

        return User::whereHas('roles', fn ($q) => $q->where('name', 'bureau_master'))->value('id')
            ?? User::query()->value('id');
    }
}
