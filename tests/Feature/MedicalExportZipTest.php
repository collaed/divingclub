<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;
use ZipArchive;

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
        File::deleteDirectory($this->legacyDir.'/999');
        @unlink($this->legacyDir.'/__TEST_Flat.pdf');
        @unlink($this->legacyDir.'/DUPONT_Jean.pdf');
        @unlink($this->legacyDir.'/ALBUQUERQUE_Luis.pdf');
        @unlink($this->legacyDir.'/9 Shadab BIGDEL SHAHSAVAN 353.pdf');
        parent::tearDown();
    }

    /** @return array<int, string> entry names in the zip the response streamed */
    private function zipEntries(TestResponse $response): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'medzip');
        file_put_contents($tmp, $response->streamedContent() ?: $response->getContent());

        $zip = new ZipArchive;
        $zip->open($tmp);
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }
        $zip->close();
        unlink($tmp);

        return $entries;
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

    private function memberWithName(string $first, string $last): User
    {
        $user = User::create([
            'username' => 'med'.uniqid(), 'primary_email' => 'med'.uniqid().'@test.com',
            'password' => 'Password1', 'role_id' => 2, 'status_id' => 1, 'email_verified_at' => now(),
        ]);
        $user->assignRole('member');
        MemberDetail::create(['user_id' => $user->id, 'first_name' => $first, 'last_name' => $last]);

        return $user;
    }

    public function test_db_tracked_certificate_filename_is_lastname_firstname_date(): void
    {
        $user = $this->memberWithName('Jean', 'Dupont');
        Storage::disk('local')->put('documents/cert.pdf', '%PDF-1.4 fake');
        Document::create([
            'user_id' => $user->id, 'category' => 'medical', 'is_current' => true,
            'file_path' => 'documents/cert.pdf', 'original_filename' => 'cert.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 1, 'date_established' => '2026-03-15',
        ]);

        $response = $this->actingAs($this->createBureauUser())->get('/admin/medical-certificates');

        $this->assertContains('DUPONT Jean 2026-03-15.pdf', $this->zipEntries($response));
    }

    public function test_only_the_current_certificate_is_exported_not_a_superseded_one(): void
    {
        $user = $this->memberWithName('Jean', 'Dupont');
        Storage::disk('local')->put('documents/old.pdf', 'old');
        Storage::disk('local')->put('documents/current.pdf', 'current');
        Document::create([
            'user_id' => $user->id, 'category' => 'medical', 'is_current' => false,
            'file_path' => 'documents/old.pdf', 'original_filename' => 'old.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 1, 'date_established' => '2025-01-01',
        ]);
        Document::create([
            'user_id' => $user->id, 'category' => 'medical', 'is_current' => true,
            'file_path' => 'documents/current.pdf', 'original_filename' => 'current.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 1, 'date_established' => '2026-03-15',
        ]);

        $response = $this->actingAs($this->createBureauUser())->get('/admin/medical-certificates');
        $entries = $this->zipEntries($response);

        $this->assertCount(1, $entries, 'Only the current certificate should be in the export.');
        $this->assertContains('DUPONT Jean 2026-03-15.pdf', $entries);
    }

    public function test_only_the_newest_legacy_file_for_a_person_is_exported(): void
    {
        // Same person can end up with a leftover flat-layout scan AND a
        // newer nested-layout one (the two historic layouts this exporter
        // supports) — only the newer copy should survive.
        File::ensureDirectoryExists($this->legacyDir.'/999');
        File::put($this->legacyDir.'/DUPONT_Jean.pdf', 'old scan');
        touch($this->legacyDir.'/DUPONT_Jean.pdf', now()->subYear()->timestamp);
        File::put($this->legacyDir.'/999/DUPONT_Jean.pdf', 'newer scan');
        touch($this->legacyDir.'/999/DUPONT_Jean.pdf', now()->timestamp);

        $response = $this->actingAs($this->createBureauUser())->get('/admin/medical-certificates');
        $entries = $this->zipEntries($response);

        $this->assertCount(1, $entries, 'Only one legacy file per person should be exported.');
        $this->assertSame('999 DUPONT_Jean.pdf', $entries[0]);
    }

    /**
     * A file imported by SyncOldEvents gets both a nested legacy file AND a
     * current Document row pointing at that exact file. The legacy-name
     * parser assumed "LASTNAME_Firstname" order, but files synced onto this
     * server are actually "Firstname LASTNAME {old-system-id}" — the
     * reversed order broke the by-name "already in DB" check, so the file
     * was exported twice: once correctly renamed via the Document row, and
     * once again under its old, un-renamed legacy entry.
     */
    public function test_a_nested_legacy_file_already_tracked_by_a_document_is_not_duplicated(): void
    {
        $user = $this->memberWithName('Louis', 'Chaussard');
        File::ensureDirectoryExists($this->legacyDir."/{$user->id}");
        File::put($this->legacyDir."/{$user->id}/Louis CHAUSSARD 286.pdf", '%PDF-1.4 fake');
        Document::create([
            'user_id' => $user->id, 'category' => 'medical', 'is_current' => true,
            'file_path' => "private/medical/{$user->id}/Louis CHAUSSARD 286.pdf",
            'original_filename' => 'Louis CHAUSSARD 286.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 1, 'date_established' => '2026-03-15',
        ]);

        $response = $this->actingAs($this->createBureauUser())->get('/admin/medical-certificates');
        $entries = $this->zipEntries($response);

        $this->assertCount(1, $entries, 'The file is already tracked by a Document row and must not also ride along via the legacy branch.');
        $this->assertSame('CHAUSSARD Louis 2026-03-15.pdf', $entries[0]);

        File::deleteDirectory($this->legacyDir."/{$user->id}");
    }

    /**
     * A legacy file whose person has a current Document under a *different*
     * physical path (e.g. re-uploaded through the profile after the legacy
     * import) was not caught by the old exact-path "already tracked" check,
     * so the superseded legacy scan rode along next to the correct one.
     */
    public function test_a_legacy_file_is_excluded_when_the_same_person_already_has_a_current_document_elsewhere(): void
    {
        $user = $this->memberWithName('Luis', 'Albuquerque');
        Storage::disk('local')->put('documents/reuploaded.pdf', 'current');
        Document::create([
            'user_id' => $user->id, 'category' => 'medical', 'is_current' => true,
            'file_path' => 'documents/reuploaded.pdf', 'original_filename' => 'reuploaded.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 1, 'date_established' => '2025-11-21',
        ]);
        File::put($this->legacyDir.'/ALBUQUERQUE_Luis.pdf', 'orphaned old scan');

        $response = $this->actingAs($this->createBureauUser())->get('/admin/medical-certificates');
        $entries = $this->zipEntries($response);

        $this->assertCount(1, $entries, 'The orphaned legacy scan must not ride along once the person has a current DB-tracked document.');
        $this->assertSame('ALBUQUERQUE Luis 2025-11-21.pdf', $entries[0]);
    }

    public function test_an_untracked_legacy_file_matching_a_member_is_renamed_to_the_standard_convention(): void
    {
        $user = $this->memberWithName('Shadab', 'Bigdel Shahsavan');
        File::put($this->legacyDir.'/9 Shadab BIGDEL SHAHSAVAN 353.pdf', '%PDF-1.4 fake');
        touch($this->legacyDir.'/9 Shadab BIGDEL SHAHSAVAN 353.pdf', mktime(0, 0, 0, 6, 15, 2026));

        $response = $this->actingAs($this->createBureauUser())->get('/admin/medical-certificates');
        $entries = $this->zipEntries($response);

        $this->assertCount(1, $entries);
        $this->assertSame('BIGDEL SHAHSAVAN Shadab 2026-06-15.pdf', $entries[0]);
    }
}
