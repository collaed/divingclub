<?php

namespace App\Jobs;

use App\Helpers\LocaleHelper;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Services\ArticleTranslationService;
use App\Services\ScheduleHeartbeat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessTranslations implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        $locales = LocaleHelper::enabledLocales();
        $svc = app(ArticleTranslationService::class);

        $new = Article::whereDoesntHave('translations')->where('is_published', true)->oldest()->first();
        if ($new) {
            $svc->translateAll($new, $locales);
            Log::info("Auto-translated new article: {$new->title}");
        }

        $stale = ArticleTranslation::where('stale', true)
            ->where('retries', '<', 3)
            ->whereNull('flagged_at')
            ->with('article')
            ->limit(5)->get();

        foreach ($stale as $t) {
            if (! $t->article) {
                continue;
            }
            try {
                $svc->translate($t->article, $t->locale);
            } catch (\Throwable $e) {
                Log::warning("Translation refresh failed: {$t->article->title} [{$t->locale}]");
            }
        }

        ArticleTranslation::where('stale', true)
            ->where('retries', '>=', 3)
            ->whereNull('flagged_at')
            ->update(['flagged_at' => now(), 'flag_reason' => 'Failed after 3 retries']);

        ArticleTranslation::where('stale', false)
            ->whereNull('flagged_at')
            ->whereNotNull('source_word_count')
            ->whereNotNull('translated_word_count')
            ->whereRaw('translated_word_count < source_word_count * 0.3 OR translated_word_count > source_word_count * 3')
            ->limit(10)
            ->each(fn ($t) => $t->update([
                'flagged_at' => now(),
                'flag_reason' => "Word count ratio: {$t->source_word_count} → {$t->translated_word_count}",
                'stale' => true,
            ]));

        ScheduleHeartbeat::beat('translations');
    }
}
