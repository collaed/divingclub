<?php

namespace App\Services;

use Carbon\Carbon;
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
     * Create a full backup (DB + optional storage files).
     *
     * @return array{filename: string, path: string, size: int, manifest: array}
     */
    public function create(bool $includeFiles = true): array
    {
        $timestamp = now()->format('Y-m-d-His');
        $tmpDir = storage_path("app/backups/.tmp-{$timestamp}");
        @mkdir($tmpDir, 0755, true);

        // 1. Database dump
        $this->dumpDatabase($tmpDir);

        // 2. Build manifest
        $manifest = $this->buildManifest($includeFiles);
        file_put_contents("{$tmpDir}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 3. Create tar.gz
        $filename = "backup-{$timestamp}.tar.gz";
        $tarPath = "{$this->backupDir}/{$filename}";

        $parts = ['manifest.json', $this->dbDumpFilename()];

        if ($includeFiles) {
            foreach (['public', 'private'] as $disk) {
                $src = storage_path("app/{$disk}");
                if (is_dir($src) && is_readable($src)) {
                    symlink($src, "{$tmpDir}/{$disk}");
                    $parts[] = $disk;
                }
            }
        }

        $cmd = sprintf(
            'tar czfh %s -C %s --ignore-failed-read %s 2>&1',
            escapeshellarg($tarPath),
            escapeshellarg($tmpDir),
            implode(' ', array_map('escapeshellarg', $parts))
        );
        exec($cmd, $output, $code);

        // Cleanup tmp
        @unlink("{$tmpDir}/public");
        @unlink("{$tmpDir}/private");
        @unlink("{$tmpDir}/".$this->dbDumpFilename());
        @unlink("{$tmpDir}/manifest.json");
        @rmdir($tmpDir);

        if ($code > 1) {
            Log::error('Backup tar failed: '.implode("\n", $output));
            throw new \RuntimeException('Backup creation failed');
        }

        $size = filesize($tarPath);
        Log::info("Backup created: {$filename} (".$this->humanSize($size).')');

        return ['filename' => $filename, 'path' => $tarPath, 'size' => $size, 'manifest' => $manifest];
    }

    /** List all existing backups with parsed manifests. */
    public function list(): array
    {
        $files = glob("{$this->backupDir}/backup-*.tar.gz");
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

    /** Read manifest.json from inside a tar.gz without full extraction. */
    public function readManifest(string $path): ?array
    {
        $json = shell_exec(sprintf('tar xzf %s manifest.json -O 2>/dev/null', escapeshellarg($path)));

        return $json ? json_decode($json, true) : null;
    }

    /** List files inside a backup's storage/ directory. */
    public function listFiles(string $path): array
    {
        $output = shell_exec(sprintf('tar tzf %s 2>/dev/null', escapeshellarg($path)));
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
        $files = glob("{$this->backupDir}/backup-*.tar.gz");
        rsort($files);
        $deleted = 0;
        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
            Log::info('Deleted old backup: '.basename($old));
            $deleted++;
        }

        return $deleted;
    }

    protected function dumpDatabase(string $dir): void
    {
        $driver = config('database.default');
        $conn = config("database.connections.{$driver}");

        if ($driver === 'sqlite') {
            $dbPath = $conn['database'];
            if (file_exists($dbPath)) {
                copy($dbPath, "{$dir}/database.sqlite");
            }
        } elseif ($driver === 'pgsql') {
            $dumpFile = "{$dir}/database.sql.gz";
            $env = $conn['password'] ? 'PGPASSWORD='.escapeshellarg($conn['password']).' ' : '';
            $cmd = sprintf(
                '%spg_dump -h %s -p %s -U %s %s | gzip > %s 2>&1',
                $env,
                escapeshellarg($conn['host'] ?? '127.0.0.1'),
                escapeshellarg($conn['port'] ?? '5432'),
                escapeshellarg($conn['username']),
                escapeshellarg($conn['database']),
                escapeshellarg($dumpFile)
            );
            exec($cmd, $output, $code);
            if ($code !== 0) {
                Log::error('PG dump failed: '.implode("\n", $output));
                throw new \RuntimeException('Database dump failed');
            }
        } else {
            $dumpFile = "{$dir}/database.sql.gz";
            $cmd = sprintf(
                'mysqldump --no-tablespaces -h%s -P%s -u%s %s %s | gzip > %s 2>&1',
                escapeshellarg($conn['host'] ?? '127.0.0.1'),
                escapeshellarg($conn['port'] ?? '3306'),
                escapeshellarg($conn['username']),
                $conn['password'] ? '-p'.escapeshellarg($conn['password']) : '',
                escapeshellarg($conn['database']),
                escapeshellarg($dumpFile)
            );
            exec($cmd, $output, $code);
            if ($code !== 0) {
                Log::error('DB dump failed: '.implode("\n", $output));
                throw new \RuntimeException('Database dump failed');
            }
        }
    }

    protected function dbDumpFilename(): string
    {
        return config('database.default') === 'sqlite' ? 'database.sqlite' : 'database.sql.gz';
    }

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
