<?php

use App\Jobs\SendMedicalReminders;
use App\Jobs\WeeklyBackup;
use App\Models\Article;
use App\Models\Vote;
use App\Services\ArticleTranslationService;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new SendMedicalReminders)->dailyAt('08:00');
Schedule::job(new WeeklyBackup)->weeklyOn(0, '03:00'); // Sunday 3am

// Auto-translate one untranslated article per hour
Schedule::call(function () {
    $article = Article::whereDoesntHave('translations')->where('published', true)->oldest()->first();
    if ($article) {
        app(ArticleTranslationService::class)->translateArticle($article);
    }
})->hourly();

// Auto-open/close votes
Schedule::call(function () {
    Vote::where('status', 'draft')->where('opens_at', '<=', now())->update(['status' => 'open']);
    Vote::where('status', 'open')->where('closes_at', '<=', now())->update(['status' => 'closed']);
})->everyMinute();

// Auto-purge audit logs per retention policy (monthly)
Schedule::call(function () {
    $months = (int) \App\Models\ThemeSetting::get('audit_retention_months', 24);
    if ($months > 0) {
        \App\Models\AuditLog::where('created_at', '<', now()->subMonths($months))->delete();
    }
})->monthlyOn(1, '04:00');
