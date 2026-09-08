<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

#[Group('p1')]
class MedicalExportZipTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private string $legacyDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->legacyDir = storage_path('app/private/medical');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->legacyDir.'/__test_member_42');
        File::deleteDirectory($this->legacyDir.'/__test_empty');
        @unlink($this->legacyDir.'/__TEST_Flat.pdf');
        parent::tearDown();
    }

    public function test_zip_export_skips_legacy_subdirectories_and_does_not_500(): void
    {
        // Prod layout: private/medical/<id>/<file>.pdf  + a bare dir with no files
        // (the bare dir is what made ZipArchive::close() throw "Is a directory").
        File::ensureDirectoryExists($this->legacyDir.'/__test_member_42');
        File::put($this->legacyDir.'/__test_member_42/KRAEMER_Roger.pdf', '%PDF-1.4 fake');
        File::ensureDirectoryExists($this->legacyDir.'/__test_empty');
        File::put($this->legacyDir.'/__TEST_Flat.pdf', '%PDF-1.4 fake flat');

        $response = $this->actingAs($this->createBureauUser())->get('/admin/medical-certificates');

        $response->assertOk();
        $this->assertStringContainsString(
            'zip',
            strtolower((string) ($response->headers->get('content-type').$response->headers->get('content-disposition'))),
        );
        $this->assertStringStartsWith('PK', $response->streamedContent() ?: $response->getContent());
    }
}
