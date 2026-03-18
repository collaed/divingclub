<?php

use App\Jobs\SendMedicalReminders;
use App\Jobs\WeeklyBackup;
use App\Models\Vote;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new SendMedicalReminders)->dailyAt('08:00');
Schedule::job(new WeeklyBackup)->weeklyOn(0, '03:00'); // Sunday 3am

Schedule::job(new SendMedicalReminders)->dailyAt('08:00');

// Auto-open/close votes
Schedule::call(function () {
    Vote::where('status', 'draft')->where('opens_at', '<=', now())->update(['status' => 'open']);
    Vote::where('status', 'open')->where('closes_at', '<=', now())->update(['status' => 'closed']);
})->everyMinute();
