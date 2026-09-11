<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Jobs\SendMedicalCertificateRejectedEmail;
use App\Models\Document;
use App\Models\MedicalReviewComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class MedicalReviewTest extends TestCase
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
        $user = User::create([
            'username' => 'm'.uniqid(),
            'primary_email' => 'member'.uniqid().'@example.com',
            'password' => 'Password1',
            'role_id' => 2,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('member');

        return $user;
    }

    private function pendingDoc(User $member): Document
    {
        return Document::create([
            'user_id' => $member->id,
            'category' => 'medical',
            'file_path' => 'private/medical/test.pdf',
            'original_filename' => 'cert.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'date_established' => now()->subDay(),
            'is_current' => true,
        ]);
    }

    public function test_only_bureau_can_reach_the_review_page(): void
    {
        $this->actingAs($this->member())->get(route('admin.medical-review.index'))->assertForbidden();
        $this->actingAs($this->createBureauUser())->get(route('admin.medical-review.index'))->assertOk();
    }

    public function test_pending_list_only_shows_unreviewed_current_medical_documents(): void
    {
        $member = $this->member();
        $pending = $this->pendingDoc($member);
        $verified = $this->pendingDoc($member);
        $verified->update(['is_verified' => true, 'verified_at' => now(), 'is_current' => false]);
        $rejected = $this->pendingDoc($member);
        $rejected->update(['rejected_at' => now()]);

        $response = $this->actingAs($this->createBureauUser())->get(route('admin.medical-review.index'));

        $response->assertOk()->assertSee($member->name);
        $this->assertTrue($response->viewData('pending')->contains('id', $pending->id));
        $this->assertFalse($response->viewData('pending')->contains('id', $verified->id));
        $this->assertFalse($response->viewData('pending')->contains('id', $rejected->id));
    }

    public function test_validate_without_comment_marks_verified(): void
    {
        $doc = $this->pendingDoc($this->member());
        $bureau = $this->createBureauUser();

        $this->actingAs($bureau)->post(route('admin.medical-review.validate', $doc))->assertRedirect();

        $doc->refresh();
        $this->assertTrue($doc->is_verified);
        $this->assertSame($bureau->id, $doc->verified_by);
        $this->assertNull($doc->review_comment);
        $this->assertFalse($doc->isRejected());
    }

    public function test_validate_with_comment_marks_verified_and_stores_the_comment(): void
    {
        $doc = $this->pendingDoc($this->member());

        $this->actingAs($this->createBureauUser())
            ->post(route('admin.medical-review.validate', $doc), ['comment' => 'Please resubmit next year with a sharper scan.'])
            ->assertRedirect();

        $doc->refresh();
        $this->assertTrue($doc->is_verified);
        $this->assertSame('Please resubmit next year with a sharper scan.', $doc->review_comment);
    }

    public function test_reject_requires_a_comment_and_emails_the_member(): void
    {
        Bus::fake();
        $doc = $this->pendingDoc($this->member());
        $bureau = $this->createBureauUser();

        $this->actingAs($bureau)->post(route('admin.medical-review.reject', $doc), [])
            ->assertSessionHasErrors('comment');
        $this->assertFalse($doc->fresh()->isRejected());

        $this->actingAs($bureau)
            ->post(route('admin.medical-review.reject', $doc), ['comment' => 'Scan is illegible.'])
            ->assertRedirect();

        $doc->refresh();
        $this->assertTrue($doc->isRejected());
        $this->assertSame($bureau->id, $doc->rejected_by);
        $this->assertSame('Scan is illegible.', $doc->review_comment);
        $this->assertFalse($doc->is_verified);
        Bus::assertDispatched(SendMedicalCertificateRejectedEmail::class, fn ($job) => $job->documentId === $doc->id);
    }

    public function test_a_regular_member_cannot_validate_or_reject(): void
    {
        $doc = $this->pendingDoc($this->member());
        $other = $this->member();

        $this->actingAs($other)->post(route('admin.medical-review.validate', $doc))->assertForbidden();
        $this->actingAs($other)->post(route('admin.medical-review.reject', $doc), ['comment' => 'x'])->assertForbidden();
    }

    public function test_bureau_can_manage_predefined_comments(): void
    {
        $bureau = $this->createBureauUser();

        $this->actingAs($bureau)->post(route('admin.medical-review-comments.store'), ['text' => 'Date missing on the scan.'])->assertRedirect();
        $comment = MedicalReviewComment::firstOrFail();
        $this->assertSame('Date missing on the scan.', $comment->text);

        $this->actingAs($bureau)->delete(route('admin.medical-review-comments.destroy', $comment))->assertRedirect();
        $this->assertDatabaseMissing('medical_review_comments', ['id' => $comment->id]);
    }
}
