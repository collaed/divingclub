<?php

use App\Jobs\AutoOpenCloseVotes;
use App\Jobs\CleanupClassifieds;
use App\Jobs\PollInboundMail;
use App\Jobs\ProcessTranslations;
use App\Jobs\PurgeAuditLogs;
use App\Jobs\SendEquipmentReminders;
use App\Jobs\SendMedicalReminders;
use App\Jobs\WeeklyBackup;
use App\Services\ScheduleHeartbeat;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new SendMedicalReminders)->dailyAt('08:00')->after(fn () => ScheduleHeartbeat::beat('medical-reminders'));
Schedule::job(new WeeklyBackup)->weeklyOn(0, '03:00')->after(fn () => ScheduleHeartbeat::beat('weekly-backup'));
Schedule::job(new ProcessTranslations)->hourly();
Schedule::job(new AutoOpenCloseVotes)->everyMinute();
Schedule::job(new PollInboundMail)->everyMinute();
Schedule::job(new PurgeAuditLogs)->monthlyOn(1, '04:00');
Schedule::job(new CleanupClassifieds)->monthlyOn(1, '05:00');
Schedule::job(new SendEquipmentReminders)->dailyAt('09:00');
