<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\LibraryFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
}
