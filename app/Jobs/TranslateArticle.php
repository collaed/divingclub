<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Helpers\LocaleHelper;
use App\Models\Article;
use App\Services\ArticleTranslationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TranslateArticle implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $articleId, public string $sourceHash) {}

    public function handle(ArticleTranslationService $svc): void
    {
        $article = Article::find($this->articleId);
        if (! $article) {
            return;
        }

        // Only translate if the article hasn't changed again since this job was dispatched
        if (ArticleTranslationService::sourceHash($article) !== $this->sourceHash) {
            return;
        }

        $locales = array_diff(LocaleHelper::enabledLocales(), ['fr']);
        $svc->translateAll($article, $locales);
    }
}
