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

// The old Joomla site at clubcep.eu is retired (DNS moved; /wrapp/*.php APIs
// gone), so sync:old-events has nothing to poll. The `legacy:sync` DB sync
// (LEGACY_DB_*) is kept scheduled for now — remove it too once that database
// is decommissioned.
Schedule::command('legacy:sync')->hourly()->after(fn () => ScheduleHeartbeat::beat('legacy-sync-bidi'));

Schedule::command('incoming:process')->everyTenMinutes()->after(fn () => ScheduleHeartbeat::beat('incoming-files'));

Schedule::command('tracking:prune')->dailyAt('04:30')->after(fn () => ScheduleHeartbeat::beat('tracking-prune'));

// Feeds the Horizon "Metrics" dashboard (job/queue throughput + runtime graphs).
// Without this the metrics page stays empty; retention is config/horizon.php → metrics.trim_snapshots.
Schedule::command('horizon:snapshot')->everyFifteenMinutes();
