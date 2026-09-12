<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Article;

/**
 * Flags an article's existing translations as stale when the French source
 * no longer matches what they were translated from. Lives here (not in
 * ArticleTranslationService) so App\Models\Article can call it directly from
 * a model event — Deptrac's layering allows Models -> Helpers but not
 * Models -> Services — meaning every save path (the admin form, a console
 * command, a one-off tinker/script fix) is covered, not just
 * ArticleController::update().
 */
class ArticleTranslationStaleness
{
    /**
     * Compute the same source hash ArticleTranslationService uses, without
     * depending on it (see class docblock).
     */
    public static function sourceHash(Article $article): string
    {
        return hash('xxh3', $article->title.'|'.$article->body);
    }

    /** Mark every auto-translated translation stale if the source changed. */
    public static function markIfChanged(Article $article): int
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
}
