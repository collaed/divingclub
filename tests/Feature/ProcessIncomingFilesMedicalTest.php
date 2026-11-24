<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Document;
use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * A medical certificate imported through the admin "incoming files" intake
 * must become the member's current certificate (is_current = true) and
 * demote any older one — same as every other medical-cert creation path
 * (profile upload, legacy sync). Previously it set is_current only for
 * category === 'licence_card', so an imported medical cert never became
 * current and the medical export zip never picked it up.
 */
class ProcessIncomingFilesMedicalTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private string $incomingDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->incomingDir = storage_path('app/incoming');
        File::ensureDirectoryExists($this->incomingDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->incomingDir);
        parent::tearDown();
    }

    private function member(string $first, string $last): User
    {
        $user = User::create([
            'username' => 'inc'.uniqid(), 'primary_email' => 'inc'.uniqid().'@test.com',
            'password' => 'Password1', 'role_id' => 2, 'status_id' => 1, 'email_verified_at' => now(),
        ]);
        $user->assignRole('member');
        MemberDetail::create(['user_id' => $user->id, 'first_name' => $first, 'last_name' => $last]);

        return $user;
    }

    public function test_an_imported_medical_certificate_becomes_the_current_one_and_demotes_the_old(): void
    {
        $user = $this->member('Jean', 'Dupont');
        $old = Document::create([
            'user_id' => $user->id, 'category' => 'medical', 'is_current' => true,
            'file_path' => 'documents/old.pdf', 'original_filename' => 'old.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 1,
        ]);
        File::put($this->incomingDir.'/Dupont_Jean_medical.pdf', '%PDF-1.4 fake');

        $this->artisan('incoming:process')->assertExitCode(0);

        $imported = Document::where('user_id', $user->id)->where('category', 'medical')->where('is_current', true)->first();
        $this->assertNotNull($imported, 'The imported certificate should become the current one.');
        $this->assertFalse($old->fresh()->is_current, 'The older certificate should be demoted.');
        $this->assertSame($imported->id, $old->fresh()->superseded_by);
    }
}
