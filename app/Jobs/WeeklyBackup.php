<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WeeklyBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $db = config('database.connections.mysql');
        $filename = 'backup-' . now()->format('Y-m-d-His') . '.sql.gz';
        $path = storage_path("app/backups/{$filename}");

        @mkdir(dirname($path), 0755, true);

        $cmd = sprintf(
            'mysqldump -h%s -u%s -p%s %s | gzip > %s 2>&1',
            escapeshellarg($db['host']),
            escapeshellarg($db['username']),
            escapeshellarg($db['password']),
            escapeshellarg($db['database']),
            escapeshellarg($path)
        );

        exec($cmd, $output, $code);

        if ($code !== 0) {
            Log::error("Backup failed: " . implode("\n", $output));
            return;
        }

        Log::info("Backup created: {$filename} (" . round(filesize($path) / 1024) . " KB)");

        // Retain last 4 backups
        $files = glob(storage_path('app/backups/backup-*.sql.gz'));
        rsort($files);
        foreach (array_slice($files, 4) as $old) {
            @unlink($old);
            Log::info("Deleted old backup: " . basename($old));
        }
    }
}
