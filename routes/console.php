<?php

use App\Http\Middleware\SetLocale;
use App\Jobs\SendMedicalReminders;
use App\Jobs\WeeklyBackup;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\AuditLog;
use App\Models\EquipmentLoan;
use App\Models\ThemeSetting;
use App\Models\Vote;
use App\Services\ArticleTranslationService;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Schedule::job(new SendMedicalReminders)->dailyAt('08:00');
Schedule::job(new WeeklyBackup)->weeklyOn(0, '03:00'); // Sunday 3am

// Auto-translate: new articles, stale translations, and quality checks
Schedule::call(function () {
    $locales = SetLocale::enabledLocales();
    $svc = app(ArticleTranslationService::class);

    // 1. Translate one untranslated article
    $new = Article::whereDoesntHave('translations')->where('is_published', true)->oldest()->first();
    if ($new) {
        $svc->translateAll($new, $locales);
        Log::info("Auto-translated new article: {$new->title}");
    }

    // 2. Refresh stale translations (max 5 per run, skip flagged)
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
            Log::info("Refreshed stale translation: {$t->article->title} [{$t->locale}]");
        } catch (Throwable $e) {
            Log::warning("Translation refresh failed: {$t->article->title} [{$t->locale}] — {$e->getMessage()}");
        }
    }

    // 3. Flag translations that failed 3+ times
    ArticleTranslation::where('stale', true)
        ->where('retries', '>=', 3)
        ->whereNull('flagged_at')
        ->update(['flagged_at' => now(), 'flag_reason' => 'Failed after 3 retries']);

    // 4. Flag translations with suspicious word count (check 10 per run)
    ArticleTranslation::where('stale', false)
        ->whereNull('flagged_at')
        ->whereNotNull('source_word_count')
        ->whereNotNull('translated_word_count')
        ->whereRaw('translated_word_count < source_word_count * 0.3 OR translated_word_count > source_word_count * 3')
        ->limit(10)
        ->each(function ($t) {
            $t->update([
                'flagged_at' => now(),
                'flag_reason' => "Word count ratio: {$t->source_word_count} → {$t->translated_word_count}",
                'stale' => true,
            ]);
        });
})->hourly();

// Auto-open/close votes
Schedule::call(function () {
    $opened = Vote::where('status', 'draft')->where('opens_at', '<=', now())->get();
    foreach ($opened as $vote) {
        $vote->update(['status' => 'open']);
        app(PushNotificationService::class)->sendToAll(
            __('Vote Open'),
            $vote->title,
            route('vote.show', ['token' => 'check']) // members use their token
        );
    }
    Vote::where('status', 'open')->where('closes_at', '<=', now())->update(['status' => 'closed']);
})->everyMinute();

// Auto-purge audit logs per retention policy (monthly)
Schedule::call(function () {
    $months = (int) ThemeSetting::get('audit_retention_months', 24);
    if ($months > 0) {
        AuditLog::where('created_at', '<', now()->subMonths($months))->delete();
    }
})->monthlyOn(1, '04:00');

// Expired classifieds: unpublish monthly, delete after 3 months inactive
Schedule::call(function () {
    // Unpublish expired
    Article::where('article_type', 'classified')
        ->where('is_published', true)
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->update(['is_published' => false]);

    // Delete (with images) after 3 months past expiry
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
})->monthlyOn(1, '05:00');

// Overdue equipment loan reminders
Schedule::call(function () {
    $thresholdDays = (int) ThemeSetting::get('equipment_loan_max_days', 30);
    $overdueLoans = EquipmentLoan::whereNull('returned_at')
        ->whereNotNull('expected_return_date')
        ->where('expected_return_date', '<', now())
        ->whereNull('reminder_sent_at')
        ->with(['user', 'equipment'])
        ->get();

    foreach ($overdueLoans as $loan) {
        $days = (int) $loan->expected_return_date->diffInDays(now());
        Log::info("Overdue loan: {$loan->equipment->name} → {$loan->user->name} ({$days}d overdue)");
        app(PushNotificationService::class)->sendToUser(
            $loan->user,
            __('Equipment Return Overdue'),
            __(':item was due back :date. Please return it.', [
                'item' => $loan->equipment->name,
                'date' => $loan->expected_return_date->format('d/m/Y'),
            ]),
            '/profile'
        );
        $loan->update(['reminder_sent_at' => now()]);
    }

    // Also flag loans without expected_return_date that exceed threshold
    EquipmentLoan::whereNull('returned_at')
        ->whereNull('expected_return_date')
        ->where('loaned_at', '<', now()->subDays($thresholdDays))
        ->whereNull('reminder_sent_at')
        ->with(['user', 'equipment'])
        ->each(function ($loan) {
            app(PushNotificationService::class)->sendToBureau(
                __('Long Equipment Loan'),
                __(':item loaned to :name on :date', [
                    'item' => $loan->equipment->name,
                    'name' => $loan->user->name,
                    'date' => $loan->loaned_at->format('d/m/Y'),
                ]),
                '/admin/equipment/'.$loan->equipment_id
            );
            $loan->update(['reminder_sent_at' => now()]);
        });
})->dailyAt('09:00');
