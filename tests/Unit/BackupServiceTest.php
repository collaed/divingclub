<?php

namespace Tests\Unit;

use App\Services\BackupService;
use ReflectionMethod;
use Tests\TestCase;

class BackupServiceTest extends TestCase
{
    public function test_human_size_bytes(): void
    {
        $this->assertSame('500 B', $this->invokeHumanSize(500));
    }

    public function test_human_size_kilobytes(): void
    {
        $this->assertSame('1 KB', $this->invokeHumanSize(1024));
    }

    public function test_human_size_megabytes(): void
    {
        $this->assertSame('1.5 MB', $this->invokeHumanSize(1572864));
    }

    public function test_human_size_gigabytes(): void
    {
        $this->assertSame('2 GB', $this->invokeHumanSize(2147483648));
    }

    public function test_human_size_zero(): void
    {
        $this->assertSame('0 B', $this->invokeHumanSize(0));
    }

    public function test_db_dump_filename_matches_driver(): void
    {
        $service = new BackupService;
        $method = new ReflectionMethod(BackupService::class, 'dbDumpFilename');

        $result = $method->invoke($service);

        $this->assertContains($result, ['database.sql.gz', 'database.sqlite']);
    }

    private function invokeHumanSize(int $bytes): string
    {
        $service = new BackupService;
        $method = new ReflectionMethod(BackupService::class, 'humanSize');

        return $method->invoke($service, $bytes);
    }
}
