<?php

namespace Tests\Unit;

use App\Services\BackupService;
use PHPUnit\Framework\Attributes\Group;
use ReflectionMethod;
use Tests\TestCase;

#[Group('p1')]
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

    public function test_backup_dir_is_created(): void
    {
        $service = new BackupService;
        $this->assertDirectoryExists(storage_path('app/backups'));
    }

    private function invokeHumanSize(int $bytes): string
    {
        $service = new BackupService;
        $method = new ReflectionMethod(BackupService::class, 'humanSize');

        return $method->invoke($service, $bytes);
    }
}
