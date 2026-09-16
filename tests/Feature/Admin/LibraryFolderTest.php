<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\LibraryFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * A folder in the library only exists as a byproduct of some LibraryFile
 * having that folder value — createFolder() used to just redirect without
 * persisting anything, so an empty "created" folder vanished the moment you
 * navigated away. It now writes a hidden ".folder" placeholder, matching
 * the working pattern already used by DocumentBrowserController.
 */
#[Group('p1')]
class LibraryFolderTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_creating_a_folder_persists_it_via_a_placeholder(): void
    {
        $bureau = $this->createBureauUser();

        $this->actingAs($bureau)
            ->post(route('admin.library.create-folder'), ['parent' => '/', 'name' => 'Minutes'])
            ->assertRedirect();

        $this->assertDatabaseHas('library_files', [
            'folder' => '/Minutes', 'original_name' => '.folder',
        ]);

        // Still there on a fresh request — not just an in-memory redirect.
        $response = $this->actingAs($bureau)->get(route('admin.library.index'))->assertOk();
        $this->assertTrue($response->viewData('folders')->contains('/Minutes'));
    }

    public function test_placeholder_is_hidden_from_the_file_list_and_counts(): void
    {
        $bureau = $this->createBureauUser();
        $this->actingAs($bureau)->post(route('admin.library.create-folder'), ['parent' => '/', 'name' => 'Empty']);

        $response = $this->actingAs($bureau)->get(route('admin.library.index', ['folder' => '/Empty']))->assertOk();

        $this->assertTrue($response->viewData('files')->isEmpty());
        $response->assertDontSee('.folder');
    }

    public function test_uploading_into_a_placeholder_folder_removes_the_placeholder(): void
    {
        $bureau = $this->createBureauUser();
        $this->actingAs($bureau)->post(route('admin.library.create-folder'), ['parent' => '/', 'name' => 'Minutes']);
        $this->assertDatabaseHas('library_files', ['folder' => '/Minutes', 'original_name' => '.folder']);

        $this->actingAs($bureau)->post(route('admin.library.upload'), [
            'files' => [UploadedFile::fake()->create('agenda.pdf', 10)],
            'folder' => '/Minutes',
            'visibility' => 'members',
        ])->assertRedirect();

        $this->assertDatabaseMissing('library_files', ['folder' => '/Minutes', 'original_name' => '.folder']);
        $this->assertDatabaseHas('library_files', ['folder' => '/Minutes', 'original_name' => 'agenda.pdf']);
    }

    public function test_folder_name_must_be_a_plain_name_not_a_path(): void
    {
        $bureau = $this->createBureauUser();

        $this->actingAs($bureau)
            ->post(route('admin.library.create-folder'), ['parent' => '/', 'name' => '../etc'])
            ->assertSessionHasErrors('name');
    }

    public function test_creating_an_already_existing_folder_does_not_duplicate_the_placeholder(): void
    {
        $bureau = $this->createBureauUser();
        LibraryFile::create([
            'filename' => 'x.pdf', 'original_name' => 'x.pdf', 'path' => 'library/x.pdf',
            'mime_type' => 'application/pdf', 'size' => 10, 'folder' => '/Minutes',
            'visibility' => 'members', 'uploaded_by' => $bureau->id,
        ]);

        $this->actingAs($bureau)
            ->post(route('admin.library.create-folder'), ['parent' => '/', 'name' => 'Minutes'])
            ->assertRedirect(route('admin.library.index', ['folder' => '/Minutes']));

        $this->assertDatabaseMissing('library_files', ['folder' => '/Minutes', 'original_name' => '.folder']);
    }

    public function test_renaming_a_folder_moves_every_file_under_it_including_nested_subfolders(): void
    {
        $bureau = $this->createBureauUser();
        LibraryFile::create(['filename' => 'a.pdf', 'original_name' => 'a.pdf', 'path' => 'library/a.pdf', 'mime_type' => 'application/pdf', 'size' => 1, 'folder' => '/Bureau/AG', 'visibility' => 'members', 'uploaded_by' => $bureau->id]);
        LibraryFile::create(['filename' => 'b.pdf', 'original_name' => 'b.pdf', 'path' => 'library/b.pdf', 'mime_type' => 'application/pdf', 'size' => 1, 'folder' => '/Bureau/AG/2026', 'visibility' => 'members', 'uploaded_by' => $bureau->id]);

        $this->actingAs($bureau)
            ->post(route('admin.library.folder.rename'), ['path' => '/Bureau/AG', 'name' => 'Assemblees'])
            ->assertRedirect(route('admin.library.index', ['folder' => '/Bureau/Assemblees']));

        $this->assertDatabaseHas('library_files', ['original_name' => 'a.pdf', 'folder' => '/Bureau/Assemblees']);
        $this->assertDatabaseHas('library_files', ['original_name' => 'b.pdf', 'folder' => '/Bureau/Assemblees/2026']);
        $this->assertDatabaseMissing('library_files', ['folder' => '/Bureau/AG']);
    }

    public function test_renaming_a_folder_to_an_existing_name_is_rejected(): void
    {
        $bureau = $this->createBureauUser();
        LibraryFile::create(['filename' => 'a.pdf', 'original_name' => 'a.pdf', 'path' => 'library/a.pdf', 'mime_type' => 'application/pdf', 'size' => 1, 'folder' => '/One', 'visibility' => 'members', 'uploaded_by' => $bureau->id]);
        LibraryFile::create(['filename' => 'b.pdf', 'original_name' => 'b.pdf', 'path' => 'library/b.pdf', 'mime_type' => 'application/pdf', 'size' => 1, 'folder' => '/Two', 'visibility' => 'members', 'uploaded_by' => $bureau->id]);

        $this->actingAs($bureau)
            ->post(route('admin.library.folder.rename'), ['path' => '/One', 'name' => 'Two'])
            ->assertRedirect();

        $this->assertDatabaseHas('library_files', ['original_name' => 'a.pdf', 'folder' => '/One']);
    }

    public function test_deleting_a_folder_deletes_every_file_under_it_from_disk_and_db(): void
    {
        Storage::fake('local');
        $bureau = $this->createBureauUser();
        $keptPath = 'library/kept.pdf';
        $nestedPath = 'library/nested.pdf';
        Storage::disk('local')->put($keptPath, 'x');
        Storage::disk('local')->put($nestedPath, 'x');
        LibraryFile::create(['filename' => 'kept.pdf', 'original_name' => 'kept.pdf', 'path' => $keptPath, 'mime_type' => 'application/pdf', 'size' => 1, 'folder' => '/Old', 'visibility' => 'members', 'uploaded_by' => $bureau->id]);
        LibraryFile::create(['filename' => 'nested.pdf', 'original_name' => 'nested.pdf', 'path' => $nestedPath, 'mime_type' => 'application/pdf', 'size' => 1, 'folder' => '/Old/Sub', 'visibility' => 'members', 'uploaded_by' => $bureau->id]);
        $untouched = LibraryFile::create(['filename' => 'c.pdf', 'original_name' => 'c.pdf', 'path' => 'library/c.pdf', 'mime_type' => 'application/pdf', 'size' => 1, 'folder' => '/Elsewhere', 'visibility' => 'members', 'uploaded_by' => $bureau->id]);

        $this->actingAs($bureau)
            ->delete(route('admin.library.folder.delete'), ['path' => '/Old'])
            ->assertRedirect(route('admin.library.index', ['folder' => '/']));

        $this->assertDatabaseMissing('library_files', ['folder' => '/Old']);
        $this->assertDatabaseMissing('library_files', ['folder' => '/Old/Sub']);
        Storage::disk('local')->assertMissing($keptPath);
        Storage::disk('local')->assertMissing($nestedPath);
        $this->assertDatabaseHas('library_files', ['id' => $untouched->id]);
    }

    public function test_cannot_delete_the_root_folder(): void
    {
        $bureau = $this->createBureauUser();

        $this->actingAs($bureau)
            ->delete(route('admin.library.folder.delete'), ['path' => '/'])
            ->assertStatus(400);
    }

    public function test_copying_a_file_duplicates_the_stored_file_and_db_row(): void
    {
        Storage::fake('local');
        $bureau = $this->createBureauUser();
        $path = 'library/orig.pdf';
        Storage::disk('local')->put($path, 'contents');
        $original = LibraryFile::create(['filename' => 'orig.pdf', 'original_name' => 'orig.pdf', 'path' => $path, 'mime_type' => 'application/pdf', 'size' => 8, 'folder' => '/Docs', 'visibility' => 'members', 'description' => 'desc', 'uploaded_by' => $bureau->id]);

        $this->actingAs($bureau)
            ->post(route('admin.library.copy', $original))
            ->assertRedirect();

        $this->assertSame(2, LibraryFile::where('original_name', 'orig.pdf')->count());
        $copy = LibraryFile::where('original_name', 'orig.pdf')->where('id', '!=', $original->id)->first();
        $this->assertNotSame($original->path, $copy->path);
        $this->assertSame('/Docs', $copy->folder);
        $this->assertSame('desc', $copy->description);
        Storage::disk('local')->assertExists($copy->path);
    }
}
