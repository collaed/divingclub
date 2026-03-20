<?php

namespace App\Jobs;

use App\Services\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WeeklyBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(BackupService $backup): void
    {
        try {
            $result = $backup->create(includeFiles: true);
            $pruned = $backup->prune((int) config('backup.retention', 4));

            Log::info("Weekly backup: {$result['filename']} ({$result['manifest']['storage_size_human']} files + DB), pruned {$pruned} old");
        } catch (\Throwable $e) {
            Log::error("Weekly backup failed: {$e->getMessage()}");
        }
    }
}
