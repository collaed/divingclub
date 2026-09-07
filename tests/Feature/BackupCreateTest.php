<?php

namespace Tests\Feature;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('p1')]
class BackupCreateTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $createdFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }

    public function test_create_finds_archive_on_the_configured_backup_disk(): void
    {
        Storage::fake('backup');
        config(['backup.backup.name' => 'DivingClub']);

        // Stand in for spatie's backup:run: drop an archive where the `backup`
        // disk (not storage/app/private) actually points.
        $this->fakeBackupRun(function (): void {
            Storage::disk('backup')->put('DivingClub/backup-2026-09-07-09-10-46.zip', 'PK-fake-zip');
        });

        $result = app(BackupService::class)->create(includeFiles: false);
        $this->createdFiles[] = $result['path'];

        $this->assertMatchesRegularExpression('/^backup-\d{4}-\d{2}-\d{2}-\d{6}\.zip$/', $result['filename']);
        $this->assertSame(storage_path('app/backups').'/'.$result['filename'], $result['path']);
        $this->assertFileExists($result['path']);
        $this->assertSame('PK-fake-zip', file_get_contents($result['path']));

        $this->assertArrayHasKey('driver', $result['manifest']);
        $this->assertFalse($result['manifest']['includes_files']);

        // The archive is moved out of the spatie folder, not copied.
        $this->assertSame([], Storage::disk('backup')->files('DivingClub'));
    }

    public function test_create_reports_the_real_directory_when_no_archive_is_produced(): void
    {
        Storage::fake('backup');
        config(['backup.backup.name' => 'DivingClub']);

        $this->fakeBackupRun(fn () => null); // succeeds but writes nothing

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(Storage::disk('backup')->path('DivingClub'));

        app(BackupService::class)->create();
    }

    public function test_create_surfaces_the_backup_run_exit_code_and_output(): void
    {
        Storage::fake('backup');

        $this->fakeBackupRun(function (): int {
            /** @var Command $this */
            $this->line('pg_dump: connection to database failed');

            return 1;
        });

        try {
            app(BackupService::class)->create();
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('exit 1', $e->getMessage());
            $this->assertStringContainsString('pg_dump: connection to database failed', $e->getMessage());
        }
    }

    public function test_backups_directory_is_created_on_construction(): void
    {
        File::deleteDirectory(storage_path('app/backups'));

        app(BackupService::class);

        $this->assertDirectoryExists(storage_path('app/backups'));
    }

    /**
     * Replace the real `backup:run` command with a stub for the duration of a test.
     * The handler is rebound to the command instance so it can write captured output.
     */
    private function fakeBackupRun(\Closure $handler): void
    {
        Artisan::command('backup:run {--only-db} {--disable-notifications} {--disable-cleanup}', function () use ($handler): int {
            $bound = \Closure::bind($handler, $this, static::class);
            $result = $bound();

            return is_int($result) ? $result : 0;
        });
    }
}
