<?php

use App\Jobs\AutoOpenCloseVotes;
use App\Jobs\CleanupClassifieds;
use App\Jobs\PollInboundMail;
use App\Jobs\ProcessTranslations;
use App\Jobs\PurgeAuditLogs;
use App\Jobs\SendEquipmentReminders;
use App\Jobs\SendMedicalReminders;
use App\Jobs\WeeklyBackup;
use App\Services\BackupService;
use App\Services\ScheduleHeartbeat;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new SendMedicalReminders)->dailyAt('08:00')->after(fn () => ScheduleHeartbeat::beat('medical-reminders'));
Schedule::call(fn () => (new WeeklyBackup)->handle(app(BackupService::class)))->weeklyOn(0, '03:00')->after(fn () => ScheduleHeartbeat::beat('weekly-backup'));
Schedule::job(new ProcessTranslations)->hourly()->after(fn () => ScheduleHeartbeat::beat('translations'));
Schedule::job(new AutoOpenCloseVotes)->everyMinute()->after(fn () => ScheduleHeartbeat::beat('vote-auto'));
Schedule::job(new PollInboundMail)->everyMinute()->after(fn () => ScheduleHeartbeat::beat('inbound-mail'));
Schedule::job(new PurgeAuditLogs)->monthlyOn(1, '04:00')->after(fn () => ScheduleHeartbeat::beat('audit-cleanup'));
Schedule::job(new CleanupClassifieds)->monthlyOn(1, '05:00')->after(fn () => ScheduleHeartbeat::beat('classifieds-cleanup'));
Schedule::job(new SendEquipmentReminders)->dailyAt('09:00')->after(fn () => ScheduleHeartbeat::beat('equipment-reminders'));

Schedule::command('sync:old-events')->everyTenMinutes()->after(fn () => ScheduleHeartbeat::beat('joomla-sync'));

Schedule::command('legacy:sync')->hourly()->after(fn () => ScheduleHeartbeat::beat('legacy-sync-bidi'));

Schedule::command('incoming:process')->everyTenMinutes()->after(fn () => ScheduleHeartbeat::beat('incoming-files'));
