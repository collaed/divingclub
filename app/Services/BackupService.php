<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BackupService
{
    protected string $backupDir;

    public function __construct()
    {
        $this->backupDir = storage_path('app/backups');
        @mkdir($this->backupDir, 0755, true);
    }

    /**
     * Create a full backup using spatie/laravel-backup.
     *
     * @return array{filename: string, path: string, size: int, manifest: array}
     */
    public function create(bool $includeFiles = true): array
    {
        // Use spatie backup command
        $options = $includeFiles ? '' : '--only-db';
        Artisan::call("backup:run {$options} --disable-notifications");

        // Find the latest backup created by spatie (stored in storage/app/<AppName>/)
        $spatieDir = storage_path('app/'.str_replace(' ', '-', config('app.name', 'DivingClub')));
        $zips = glob("{$spatieDir}/*.zip");

        if (empty($zips)) {
            throw new \RuntimeException('Spatie backup produced no output');
        }

        rsort($zips);
        $latestZip = $zips[0];

        // Move to our backups dir with our naming convention
        $timestamp = now()->format('Y-m-d-His');
        $filename = "backup-{$timestamp}.zip";
        $destPath = "{$this->backupDir}/{$filename}";
        rename($latestZip, $destPath);

        $size = filesize($destPath);
        Log::info("Backup created via spatie: {$filename} (".$this->humanSize($size).')');

        // Offsite upload via SFTP if configured
        $this->offsiteUpload($destPath, $filename);

        return ['filename' => $filename, 'path' => $destPath, 'size' => $size, 'manifest' => null];
    }

    /** Upload backup to offsite SFTP server if configured. */
    protected function offsiteUpload(string $localPath, string $filename): void
    {
        $host = config('backup.offsite_host');
        if (! $host) {
            return;
        }

        $domain = parse_url(config('app.url'), PHP_URL_HOST) ?: 'unknown';
        $date = now()->format('Y-m-d');
        $remoteName = "dcms-bkp-{$domain}-{$date}.tar.gz";

        $key = config('backup.offsite_key', '/home/clubcep/.ssh/backup_key');
        $user = config('backup.offsite_user', 'dcms-backup');
        $dir = config('backup.offsite_dir', 'backups');

        $cmd = sprintf(
            'sftp -i %s -o StrictHostKeyChecking=no -b - %s@%s << EOF
cd %s
put %s %s
bye
EOF',
            escapeshellarg($key),
            escapeshellarg($user),
            escapeshellarg($host),
            escapeshellarg($dir),
            escapeshellarg($localPath),
            escapeshellarg($remoteName)
        );

        exec($cmd.' 2>&1', $output, $code);

        if ($code === 0) {
            Log::info("Backup uploaded offsite: {$remoteName} → {$user}@{$host}");
        } else {
            Log::warning('Offsite backup upload failed: '.implode("\n", $output));
        }
    }

    /** List all existing backups with parsed manifests. */
    public function list(): array
    {
        $files = array_merge(glob("{$this->backupDir}/backup-*.tar.gz"), glob("{$this->backupDir}/backup-*.zip"));
        rsort($files);

        return array_map(function (string $path) {
            return [
                'filename' => basename($path),
                'path' => $path,
                'size' => filesize($path),
                'size_human' => $this->humanSize(filesize($path)),
                'created_at' => Carbon::createFromTimestamp(filemtime($path)),
                'manifest' => $this->readManifest($path),
            ];
        }, $files);
    }

    /** Read manifest.json from inside a backup archive. */
    public function readManifest(string $path): ?array
    {
        if (str_ends_with($path, '.zip')) {
            $zip = new \ZipArchive;
            if ($zip->open($path) === true) {
                $json = $zip->getFromName('manifest.json');
                $zip->close();

                return $json ? json_decode($json, true) : null;
            }

            return null;
        }
        $json = shell_exec(sprintf('tar xzf %s manifest.json -O 2>/dev/null', escapeshellarg($path)));

        return $json ? json_decode($json, true) : null;
    }

    /** List files inside a backup's storage/ directory. */
    public function listFiles(string $path): array
    {
        if (str_ends_with($path, '.zip')) {
            $zip = new \ZipArchive;
            if ($zip->open($path) !== true) {
                return [];
            }
            $lines = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $lines[] = $zip->getNameIndex($i);
            }
            $zip->close();
            $output = implode("\n", $lines);
        } else {
            $output = shell_exec(sprintf('tar tzf %s 2>/dev/null', escapeshellarg($path)));
        }
        if (! $output) {
            return [];
        }

        $lines = array_filter(explode("\n", trim($output)));
        $files = [];
        foreach ($lines as $line) {
            if ((str_starts_with($line, 'public/') || str_starts_with($line, 'private/')) && ! str_ends_with($line, '/')) {
                $files[] = $line;
            }
        }

        // Group by disk/subfolder
        $grouped = [];
        foreach ($files as $f) {
            $parts = explode('/', $f, 3);
            $folder = ($parts[0] === 'private' ? '🔒 ' : '').($parts[1] ?? 'root');
            $grouped[$folder][] = $f;
        }

        return $grouped;
    }

    /** Extract a single file from backup and return its contents. */
    public function extractFile(string $backupPath, string $filePath): ?string
    {
        if (str_ends_with($backupPath, '.zip')) {
            $zip = new \ZipArchive;
            if ($zip->open($backupPath) === true) {
                $content = $zip->getFromName($filePath);
                $zip->close();

                return $content ?: null;
            }

            return null;
        }
        $content = shell_exec(sprintf(
            'tar xzf %s %s -O 2>/dev/null',
            escapeshellarg($backupPath),
            escapeshellarg($filePath)
        ));

        return $content ?: null;
    }

    /** Delete a backup file. */
    public function delete(string $filename): bool
    {
        $path = "{$this->backupDir}/{$filename}";
        if (file_exists($path) && str_starts_with(realpath($path), realpath($this->backupDir))) {
            return @unlink($path);
        }

        return false;
    }

    /** Prune old backups, keeping $keep most recent. */
    public function prune(int $keep = 4): int
    {
        $files = array_merge(glob("{$this->backupDir}/backup-*.tar.gz"), glob("{$this->backupDir}/backup-*.zip"));
        rsort($files);
        $deleted = 0;
        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
            Log::info('Deleted old backup: '.basename($old));
            $deleted++;
        }

        return $deleted;
    }

    /** Execute a shell command with additional environment variables (avoids leaking secrets in process list). */
    protected function buildManifest(bool $includeFiles): array
    {
        $tables = [];
        foreach (Schema::getTableListing() as $table) {
            $tables[$table] = DB::table($table)->count();
        }

        $storageFiles = 0;
        $storageSize = 0;
        if ($includeFiles) {
            foreach (['app/public', 'app/private'] as $dir) {
                $path = storage_path($dir);
                if (! is_dir($path) || ! is_readable($path)) {
                    continue;
                }

                try {
                    $it = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS | \RecursiveDirectoryIterator::FOLLOW_SYMLINKS),
                        \RecursiveIteratorIterator::LEAVES_ONLY,
                        \RecursiveIteratorIterator::CATCH_GET_CHILD
                    );
                    foreach ($it as $file) {
                        if ($file->isFile()) {
                            $storageFiles++;
                            $storageSize += $file->getSize();
                        }
                    }
                } catch (\UnexpectedValueException) {
                    // Skip unreadable directories
                }
            }
        }

        return [
            'version' => config('app.version', '1.0'),
            'created_at' => now()->toIso8601String(),
            'driver' => config('database.default'),
            'database' => config('database.connections.'.config('database.default').'.database'),
            'tables' => $tables,
            'total_rows' => array_sum($tables),
            'includes_files' => $includeFiles,
            'storage_files' => $storageFiles,
            'storage_size' => $storageSize,
            'storage_size_human' => $this->humanSize($storageSize),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];
    }

    protected function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < 3) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }
}
