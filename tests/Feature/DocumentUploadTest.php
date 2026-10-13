<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\LibraryFile;
use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'Member', 'slug' => 'member']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'active']);
        SpatieRole::findOrCreate('member', 'web');
    }

    public function test_documents_page_loads(): void
    {
        $user = $this->createUser();
        $this->actingAs($user)->get('/documents')->assertOk();
    }

    public function test_member_can_upload_document(): void
    {
        Storage::fake('local');
        \Mail::fake();
        $user = $this->createUser();

        $this->actingAs($user)->post('/profile/document', [
            'file' => UploadedFile::fake()->create('medical.pdf', 100, 'application/pdf'),
            'category' => 'medical',
        ])->assertRedirect();

        $this->assertDatabaseHas('documents', [
            'user_id' => $user->id,
            'category' => 'medical',
        ]);
    }

    public function test_second_upload_for_same_person_type_date_does_not_overwrite(): void
    {
        Storage::fake('local');
        \Mail::fake();
        $user = $this->createUser();

        $payload = fn (): array => [
            'file' => UploadedFile::fake()->create('medical.pdf', 100, 'application/pdf'),
            'category' => 'medical',
            'cert_type' => 'general',
            'date_established' => '2026-01-15',
        ];

        $this->actingAs($user)->post('/profile/document', $payload())->assertRedirect();
        $this->actingAs($user)->post('/profile/document', $payload())->assertRedirect();

        $paths = Document::where('user_id', $user->id)->pluck('file_path');
        $this->assertCount(2, $paths);
        $this->assertCount(2, $paths->unique(), 'each upload must keep its own file');
        foreach ($paths as $path) {
            Storage::disk('local')->assertExists($path);
        }
    }

    public function test_guest_cannot_upload_document(): void
    {
        $this->post('/profile/document', [
            'category' => 'medical',
        ])->assertRedirect('/login');
    }

    public function test_library_thumbnail_alt_text_is_the_file_name_not_a_placeholder(): void
    {
        $user = $this->createUser();
        LibraryFile::create([
            'filename' => 'photo.png',
            'original_name' => 'VacancesPhoto.png',
            'path' => 'library/photo.png',
            'mime_type' => 'image/png',
            'size' => 1024,
            'folder' => '/',
            'visibility' => 'members',
            'uploaded_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get('/documents');

        $response->assertSee('alt="VacancesPhoto.png"', false);
        $response->assertDontSee('alt="Decoration"', false);
    }

    public function test_library_visibility_badge_renders_icon_as_html_not_escaped_text(): void
    {
        $user = $this->createUser();
        LibraryFile::create([
            'filename' => 'reglement.pdf',
            'original_name' => 'Reglement.pdf',
            'path' => 'library/reglement.pdf',
            'mime_type' => 'application/pdf',
            'size' => 2048,
            'folder' => '/',
            'visibility' => 'members',
            'uploaded_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get('/documents');

        $response->assertSee('<span aria-hidden="true">', false);
        $response->assertDontSee('&lt;span aria-hidden', false);
    }

    public function test_folder_tree_only_marks_the_active_path_as_open(): void
    {
        $user = $this->createUser();
        LibraryFile::create([
            'filename' => 'cr.pdf', 'original_name' => 'CR.pdf', 'path' => 'library/cr.pdf',
            'mime_type' => 'application/pdf', 'size' => 10, 'folder' => '/Bureau/Comptes-Rendus/2026',
            'visibility' => 'members', 'uploaded_by' => $user->id,
        ]);
        LibraryFile::create([
            'filename' => 'budget.pdf', 'original_name' => 'Budget.pdf', 'path' => 'library/budget.pdf',
            'mime_type' => 'application/pdf', 'size' => 10, 'folder' => '/Compta',
            'visibility' => 'members', 'uploaded_by' => $user->id,
        ]);

        $content = $this->actingAs($user)
            ->get('/documents?folder='.urlencode('/Bureau/Comptes-Rendus/2026'))
            ->getContent();

        $this->assertMatchesRegularExpression('/data-path="\/Bureau".*?tree-arrow[^>]*>▼/s', $content, 'an ancestor of the active folder should render the open marker');
        $this->assertMatchesRegularExpression('/data-path="\/Compta".*?tree-arrow[^>]*>▶/s', $content, 'a folder outside the active path should render the closed marker');
    }

    private function createUser(): User
    {
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        $roleId = DB::table($roleTable)->where('slug', 'member')->value('id')
            ?? DB::table($roleTable)->where('name', 'member')->value('id') ?? 2;

        $u = User::create([
            'username' => fake()->userName(),
            'primary_email' => fake()->unique()->safeEmail(),
            'password' => 'Password1',
            'role_id' => $roleId,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $u->assignRole('member');
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'Test', 'last_name' => 'User']);

        return $u;
    }
}
