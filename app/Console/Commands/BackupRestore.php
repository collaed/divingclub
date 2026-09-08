<?php

declare(strict_types=1);

/**
 * Restore a backup archive produced by BackupService / spatie/laravel-backup.
 *
 * A backup zip holds `db-dumps/<connection>.sql[.gz]` plus a `private/…` and
 * `public/…` tree of stored files. This command extracts it and, on explicit
 * confirmation, replays the database dump and copies the files back under
 * `storage/app/` (which on the servers are symlinks to the data mount).
 *
 * DESTRUCTIVE. Requires --force (or an interactive "yes") before touching the
 * database. Take a fresh backup first.
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupRestore extends Command
{
    protected $signature = 'backup:restore
        {archive : Path to a backup .zip (absolute, or a filename under storage/app/backups)}
        {--only-db : Restore only the database, skip files}
        {--only-files : Restore only the files, skip the database}
        {--force : Skip the confirmation prompt (for scripted use)}';

    protected $description = 'Restore a backup archive (database dump + stored files). Destructive — see docs/BACKUP.md.';

    public function handle(): int
    {
        $archive = $this->argument('archive');
        if (! str_contains($archive, '/')) {
            $archive = storage_path('app/backups/'.$archive);
        }
        if (! is_file($archive)) {
            $this->error("Archive not found: {$archive}");

            return self::FAILURE;
        }

        $work = storage_path('app/backup-temp/restore-'.now()->format('Ymd-His'));
        File::ensureDirectoryExists($work);

        $this->info("Extracting {$archive} …");
        $zip = new \ZipArchive;
        if ($zip->open($archive) !== true) {
            $this->error('Could not open the archive.');

            return self::FAILURE;
        }
        $zip->extractTo($work);
        $zip->close();

        if ($manifest = @json_decode((string) @file_get_contents($work.'/manifest.json'), true)) {
            $this->line('  manifest: '.($manifest['total_rows'] ?? '?').' rows, '
                .($manifest['storage_files'] ?? 0).' files ('.($manifest['storage_size_human'] ?? '0 B').'), '
                .'taken '.($manifest['created_at'] ?? '?'));
        }

        if (! $this->option('force') && ! $this->confirm('This OVERWRITES the current database and files. Continue?', false)) {
            File::deleteDirectory($work);

            return self::FAILURE;
        }

        $dbOk = $this->option('only-files') ? true : $this->restoreDatabase($work);
        $filesOk = $this->option('only-db') ? true : $this->restoreFiles($work);
        $ok = $dbOk && $filesOk;

        File::deleteDirectory($work);
        $this->newLine();
        $this->{$ok ? 'info' : 'error'}($ok ? 'Restore complete.' : 'Restore finished with errors — check the output above.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function restoreDatabase(string $work): bool
    {
        $dumps = glob($work.'/db-dumps/*') ?: [];
        if ($dumps === []) {
            $this->error('No db-dumps/ in the archive.');

            return false;
        }
        $dump = $dumps[0];
        $this->info('Restoring database from '.basename($dump).' …');

        $conn = config('database.default');
        $c = config("database.connections.{$conn}");
        $sql = str_ends_with($dump, '.gz')
            ? 'gunzip -c '.escapeshellarg($dump)
            : 'cat '.escapeshellarg($dump);

        if ($conn === 'pgsql') {
            $cmd = $sql.' | PGPASSWORD='.escapeshellarg((string) $c['password'])
                .' psql -h '.escapeshellarg((string) $c['host']).' -p '.escapeshellarg((string) $c['port'])
                .' -U '.escapeshellarg((string) $c['username']).' -d '.escapeshellarg((string) $c['database']).' -v ON_ERROR_STOP=1';
        } else {
            $cmd = $sql.' | mysql -h '.escapeshellarg((string) $c['host']).' -P '.escapeshellarg((string) $c['port'])
                .' -u '.escapeshellarg((string) $c['username'])
                .(($c['password'] ?? '') !== '' ? ' -p'.escapeshellarg((string) $c['password']) : '')
                .' '.escapeshellarg((string) $c['database']);
        }

        $p = Process::fromShellCommandline($cmd, base_path(), null, null, 3600);
        $p->run(fn ($type, $buf) => $this->output->write($buf));

        return $p->isSuccessful();
    }

    private function restoreFiles(string $work): bool
    {
        $ok = true;
        foreach (['private', 'public'] as $tree) {
            $src = $work.'/'.$tree;
            if (! is_dir($src)) {
                continue;
            }
            $dest = storage_path('app/'.$tree);
            $this->info("Restoring files: {$tree}/ → {$dest}");
            // -a preserves times/perms; no --delete, so a partial archive never
            // wipes files it didn't contain.
            $p = new Process(['rsync', '-a', $src.'/', $dest.'/'], null, null, null, 3600);
            $p->run(fn ($type, $buf) => $this->output->write($buf));
            $ok = $p->isSuccessful() && $ok;
        }

        return $ok;
    }
}
