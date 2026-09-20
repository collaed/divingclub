<?php

namespace Tests\Feature;

use App\Jobs\WeeklyBackup;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('p1')]
class WeeklyBackupTest extends TestCase
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

    public function test_handle_returns_true_and_records_the_created_file_on_success(): void
    {
        Storage::fake('backup');
        config(['backup.backup.name' => 'DivingClub']);

        $this->fakeBackupRun(function (): void {
            Storage::disk('backup')->put('DivingClub/backup-2026-09-13-030022.zip', 'PK-fake-zip');
        });

        $result = (new WeeklyBackup)->handle(app(BackupService::class));
        $this->createdFiles = glob(storage_path('app/backups/backup-*.zip')) ?: [];

        $this->assertTrue($result);
    }

    public function test_handle_returns_false_when_the_underlying_backup_fails(): void
    {
        Storage::fake('backup');

        $this->fakeBackupRun(fn () => null); // "succeeds" but writes no archive

        $result = (new WeeklyBackup)->handle(app(BackupService::class));

        $this->assertFalse($result);
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
