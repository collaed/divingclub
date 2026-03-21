<?php

use App\Http\Middleware\SetLocale;
use App\Jobs\SendMedicalReminders;
use App\Jobs\WeeklyBackup;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\ThemeSetting;
use App\Models\Vote;
use App\Services\ArticleTranslationService;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new SendMedicalReminders)->dailyAt('08:00');
Schedule::job(new WeeklyBackup)->weeklyOn(0, '03:00'); // Sunday 3am

// Auto-translate one untranslated article per hour
Schedule::call(function () {
    $article = Article::whereDoesntHave('translations')->where('is_published', true)->oldest()->first();
    if ($article) {
        $locales = SetLocale::enabledLocales();
        app(ArticleTranslationService::class)->translateAll($article, $locales);
    }
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
