<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Jobs\ProcessLicenceScan;
use App\Models\Federation;
use App\Models\LicenceScan;
use App\Models\MemberLicence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

#[Group('p1')]
class LicenceScanTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function federation(): Federation
    {
        return Federation::create(['acronym' => 'FLASSA', 'full_name' => 'FLASSA', 'visibility' => 'active']);
    }

    public function test_plain_member_is_forbidden(): void
    {
        $this->actingAs($this->createMemberUser())
            ->get(route('admin.licence-scans.index'))
            ->assertForbidden();
    }

    public function test_upload_queues_one_scan_per_file_and_dispatches_processing(): void
    {
        Bus::fake();
        $fed = $this->federation();

        $this->actingAs($this->createBureauUser())
            ->post(route('admin.licence-scans.store'), [
                'federation_id' => $fed->id,
                'files' => [
                    UploadedFile::fake()->create('card1.pdf', 100, 'application/pdf'),
                    UploadedFile::fake()->create('card2.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, LicenceScan::where('federation_id', $fed->id)->count());
        Bus::assertDispatchedTimes(ProcessLicenceScan::class, 2);
    }

    public function test_review_index_only_lists_scans_needing_review(): void
    {
        $fed = $this->federation();
        $uploader = $this->createBureauUser();
        $needsReview = LicenceScan::create(['federation_id' => $fed->id, 'original_filename' => 'a.pdf', 'file_path' => 'x', 'status' => 'needs_review', 'uploaded_by' => $uploader->id]);
        $applied = LicenceScan::create(['federation_id' => $fed->id, 'original_filename' => 'b.pdf', 'file_path' => 'y', 'status' => 'applied', 'uploaded_by' => $uploader->id]);

        $response = $this->actingAs($this->createBureauUser())->get(route('admin.licence-scans.index'));

        $response->assertOk();
        $this->assertTrue($response->viewData('needsReview')->contains('id', $needsReview->id));
        $this->assertFalse($response->viewData('needsReview')->contains('id', $applied->id));
    }

    public function test_assign_creates_the_member_licence_and_marks_the_scan_applied(): void
    {
        $fed = $this->federation();
        $member = $this->createMemberUser();
        $bureau = $this->createBureauUser();
        $scan = LicenceScan::create(['federation_id' => $fed->id, 'original_filename' => 'a.pdf', 'file_path' => 'x', 'status' => 'needs_review', 'uploaded_by' => $bureau->id]);

        $this->actingAs($bureau)
            ->post(route('admin.licence-scans.assign', $scan), ['user_id' => $member->id, 'licence_number' => '12345', 'year' => '2026'])
            ->assertRedirect();

        $scan->refresh();
        $this->assertSame('applied', $scan->status);
        $this->assertSame($member->id, $scan->matched_user_id);
        $this->assertSame($bureau->id, $scan->reviewed_by);

        $licence = MemberLicence::where('user_id', $member->id)->where('federation_id', $fed->id)->first();
        $this->assertNotNull($licence);
        $this->assertSame('12345', $licence->licence_number);
        $this->assertSame('2026', $licence->season);
        // The scan's file_path ('x') doesn't exist on disk — issuance-date
        // extraction must degrade to null, not throw.
        $this->assertNull($licence->card_issued_at);
    }

    public function test_destroy_marks_the_scan_discarded_without_assigning_anyone(): void
    {
        $fed = $this->federation();
        $uploader = $this->createBureauUser();
        $scan = LicenceScan::create(['federation_id' => $fed->id, 'original_filename' => 'a.pdf', 'file_path' => 'x', 'status' => 'needs_review', 'uploaded_by' => $uploader->id]);

        $this->actingAs($this->createBureauUser())
            ->delete(route('admin.licence-scans.destroy', $scan))
            ->assertRedirect();

        $scan->refresh();
        $this->assertSame('discarded', $scan->status);
        $this->assertNull($scan->matched_user_id);
        $this->assertSame(0, MemberLicence::count());
    }

    public function test_a_member_who_is_not_bureau_cannot_assign_or_discard(): void
    {
        $fed = $this->federation();
        $target = $this->createMemberUser();
        $uploader = $this->createBureauUser();
        $scan = LicenceScan::create(['federation_id' => $fed->id, 'original_filename' => 'a.pdf', 'file_path' => 'x', 'status' => 'needs_review', 'uploaded_by' => $uploader->id]);

        $this->actingAs($this->createMemberUser())
            ->post(route('admin.licence-scans.assign', $scan), ['user_id' => $target->id])
            ->assertForbidden();

        $this->actingAs($this->createMemberUser())
            ->delete(route('admin.licence-scans.destroy', $scan))
            ->assertForbidden();
    }
}
