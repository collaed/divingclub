<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Document;
use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class ProfileMedicalDocumentDeleteTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function member(): User
    {
        $u = User::factory()->create();
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'T', 'last_name' => 'U']);

        return $u;
    }

    public function test_bureau_can_delete_a_superseded_certificate(): void
    {
        $member = $this->member();
        $doc = Document::create([
            'user_id' => $member->id, 'category' => 'medical', 'file_path' => 'private/medical/old.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => '1024',
            'original_filename' => 'old.pdf', 'is_current' => false,
        ]);

        $this->actingAs($this->createBureauUser())
            ->delete(route('profile.document.destroy', $doc))
            ->assertRedirect();

        $this->assertSoftDeleted('documents', ['id' => $doc->id]);
    }

    public function test_the_current_certificate_cannot_be_deleted(): void
    {
        $member = $this->member();
        $doc = Document::create([
            'user_id' => $member->id, 'category' => 'medical', 'file_path' => 'private/medical/current.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => '1024',
            'original_filename' => 'current.pdf', 'is_current' => true,
        ]);

        $this->actingAs($this->createBureauUser())
            ->delete(route('profile.document.destroy', $doc))
            ->assertForbidden();

        $this->assertDatabaseHas('documents', ['id' => $doc->id, 'deleted_at' => null]);
    }

    public function test_a_plain_member_cannot_delete_any_certificate(): void
    {
        $member = $this->member();
        $doc = Document::create([
            'user_id' => $member->id, 'category' => 'medical', 'file_path' => 'private/medical/old.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => '1024',
            'original_filename' => 'old.pdf', 'is_current' => false,
        ]);

        $this->actingAs($member)
            ->delete(route('profile.document.destroy', $doc))
            ->assertForbidden();
    }

    public function test_force_deleting_a_superseded_document_from_trash_also_removes_its_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('medical/old.pdf', 'fake-bytes');
        $member = $this->member();
        $doc = Document::create([
            'user_id' => $member->id, 'category' => 'medical', 'file_path' => 'medical/old.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => '1024',
            'original_filename' => 'old.pdf', 'is_current' => false,
        ]);
        $doc->delete();

        $this->actingAs($this->createBureauUser())
            ->delete(route('admin.trash.force-delete', ['documents', $doc->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('documents', ['id' => $doc->id]);
        Storage::disk('local')->assertMissing('medical/old.pdf');
    }
}
