<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\ThemeSetting;
use App\Services\ScheduleHeartbeat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class PurgeAuditLogs implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        $months = (int) ThemeSetting::get('audit_retention_months', 24);
        if ($months > 0) {
            AuditLog::where('created_at', '<', now()->subMonths($months))->delete();
        }

        ScheduleHeartbeat::beat('audit-purge');
    }
}
