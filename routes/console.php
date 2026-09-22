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
Schedule::call(function (): void {
    if ((new WeeklyBackup)->handle(app(BackupService::class))) {
        ScheduleHeartbeat::beat('weekly-backup');
    } else {
        ScheduleHeartbeat::fail('weekly-backup', 'Weekly backup failed — check the Laravel log for details.');
    }
})->weeklyOn(0, '03:00');
Schedule::command('members:mark-honoraires-paid')->dailyAt('06:00')->after(fn () => ScheduleHeartbeat::beat('honoraires-paid'));
// No ->after(beat) here: the command already calls ScheduleHeartbeat::beat()/fail()
// itself based on its real outcome (see ExtractComptesRendusActions::handle()) — a
// bare ->after() would unconditionally overwrite that with a success, masking a
// real failure (e.g. the AI call failing) from the dashboard. Same fix as WeeklyBackup.
Schedule::command('compte-rendus:extract-actions')->everyThreeHours();
Schedule::job(new ProcessTranslations)->hourly()->after(fn () => ScheduleHeartbeat::beat('translations'));
Schedule::job(new AutoOpenCloseVotes)->everyMinute()->after(fn () => ScheduleHeartbeat::beat('vote-auto'));
Schedule::job(new PollInboundMail)->everyMinute()->after(fn () => ScheduleHeartbeat::beat('inbound-mail'));
Schedule::job(new PurgeAuditLogs)->monthlyOn(1, '04:00')->after(fn () => ScheduleHeartbeat::beat('audit-cleanup'));
Schedule::job(new CleanupClassifieds)->monthlyOn(1, '05:00')->after(fn () => ScheduleHeartbeat::beat('classifieds-cleanup'));
Schedule::job(new SendEquipmentReminders)->dailyAt('09:00')->after(fn () => ScheduleHeartbeat::beat('equipment-reminders'));

// The old Joomla site moved to np.clubcep.eu and both sync directions are
// suspended: sync:old-events (HTTP, /wrapp/*.php — gone) and legacy:sync
// (bidirectional DB sync over LEGACY_DB_*). Re-add either only if the legacy
// integration is deliberately revived.

Schedule::command('incoming:process')->everyTenMinutes()->after(fn () => ScheduleHeartbeat::beat('incoming-files'));

Schedule::command('tracking:prune')->dailyAt('04:30')->after(fn () => ScheduleHeartbeat::beat('tracking-prune'));

// Feeds the Horizon "Metrics" dashboard (job/queue throughput + runtime graphs).
// Without this the metrics page stays empty; retention is config/horizon.php → metrics.trim_snapshots.
Schedule::command('horizon:snapshot')->everyFifteenMinutes();

// Feeds the admin dashboard's "Cloudflare AI Usage" history chart. Requires
// the CLOUDFLARE_API_TOKEN to carry "Account Analytics: Read" — without it
// this is a no-op (see CloudflareUsageService).
Schedule::command('cloudflare:sync-usage')->dailyAt('05:00')->after(fn () => ScheduleHeartbeat::beat('cloudflare-usage'));

Schedule::command('events:evaluate-automation')->everyFifteenMinutes()->after(fn () => ScheduleHeartbeat::beat('event-automation'));
