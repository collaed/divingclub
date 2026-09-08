<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_embed_manifest_reflects_real_archive_contents(): void
    {
        $dir = storage_path('app/backup-temp');
        @mkdir($dir, 0755, true);
        $zipPath = $dir.'/test-'.uniqid().'.zip';

        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('db-dumps/postgresql-test.sql.gz', str_repeat('x', 500));
        $zip->addFromString('private/medical/cert-1.pdf', str_repeat('a', 1000));
        $zip->addFromString('private/documents/doc-1.pdf', str_repeat('b', 2000));
        $zip->addFromString('public/newsletters/', '');
        $zip->close();

        $svc = new BackupService;
        $ref = new \ReflectionMethod($svc, 'embedManifest');
        $ref->setAccessible(true);
        /** @var array<string, mixed> $manifest */
        $manifest = $ref->invoke($svc, $zipPath, true);

        // db-dumps/ and directory entries are not counted; the two pdfs are
        $this->assertSame(2, $manifest['storage_files']);
        $this->assertSame(3000, $manifest['storage_size']);
        $this->assertTrue($manifest['includes_files']);
        $this->assertArrayHasKey('tables', $manifest);

        // manifest.json is now inside the archive
        $check = new \ZipArchive;
        $check->open($zipPath);
        $embedded = json_decode((string) $check->getFromName('manifest.json'), true);
        $check->close();
        $this->assertSame(2, $embedded['storage_files']);

        @unlink($zipPath);
    }

    public function test_db_only_backup_reports_zero_files(): void
    {
        $dir = storage_path('app/backup-temp');
        @mkdir($dir, 0755, true);
        $zipPath = $dir.'/test-'.uniqid().'.zip';

        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('db-dumps/postgresql-test.sql.gz', str_repeat('x', 500));
        $zip->close();

        $svc = new BackupService;
        $ref = new \ReflectionMethod($svc, 'embedManifest');
        $ref->setAccessible(true);
        $manifest = $ref->invoke($svc, $zipPath, false);

        $this->assertSame(0, $manifest['storage_files']);
        $this->assertFalse($manifest['includes_files']);

        @unlink($zipPath);
    }
}
