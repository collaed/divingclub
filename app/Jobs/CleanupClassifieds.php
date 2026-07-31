<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Article;
use App\Services\ScheduleHeartbeat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupClassifieds implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        Article::where('article_type', 'classified')
            ->where('is_published', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['is_published' => false]);

        $stale = Article::where('article_type', 'classified')
            ->where('expires_at', '<', now()->subMonths(3))
            ->get();

        foreach ($stale as $ad) {
            if ($ad->featured_image) {
                Storage::disk('public')->delete($ad->featured_image);
            }
            $ad->delete();
        }

        if ($stale->count()) {
            Log::info("Classifieds cleanup: deleted {$stale->count()} ads expired >3 months.");
        }

        ScheduleHeartbeat::beat('classifieds-cleanup', $stale->count() ? "Deleted {$stale->count()}" : null);
    }
}
