<?php

namespace Tests\Feature;

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

    public function test_guest_cannot_upload_document(): void
    {
        $this->post('/profile/document', [
            'category' => 'medical',
        ])->assertRedirect('/login');
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
