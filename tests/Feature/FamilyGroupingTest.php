<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FamilyInvite;
use App\Models\GuardianLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class FamilyGroupingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_propose_then_confirm_links_two_adults(): void
    {
        $a = $this->createMemberUser();
        $b = $this->createMemberUser();

        $this->actingAs($a)->post(route('family.propose'), ['member_id' => $b->id])->assertRedirect();
        $invite = FamilyInvite::first();
        $this->assertSame('pending', $invite->status);
        $this->assertNull($a->detail->fresh()->family_id);

        $this->actingAs($b)->post(route('family.confirm', $invite))->assertRedirect();

        $this->assertNotNull($a->detail->fresh()->family_id);
        $this->assertSame($a->detail->fresh()->family_id, $b->detail->fresh()->family_id);

        // refresh() reloads currently-loaded relations in place (unlike fresh(),
        // which returns an untouched new instance) — needed since ->detail was
        // already cached (null) by the assertions above.
        $a->refresh();
        $b->refresh();
        $this->assertTrue($a->canActFor($b));
        $this->assertTrue($b->canActFor($a));
    }

    public function test_propose_then_decline_links_nothing(): void
    {
        $a = $this->createMemberUser();
        $b = $this->createMemberUser();

        $this->actingAs($a)->post(route('family.propose'), ['member_id' => $b->id]);
        $invite = FamilyInvite::first();

        $this->actingAs($b)->post(route('family.decline', $invite))->assertRedirect();

        $this->assertSame('declined', $invite->fresh()->status);
        $this->assertNull($a->detail->fresh()->family_id);
        $this->assertNull($b->detail->fresh()->family_id);
    }

    public function test_minor_can_only_be_added_with_an_existing_guardian_link(): void
    {
        $adult = $this->createMemberUser();
        $minor = $this->createMemberUser();
        $minor->detail->update(['date_of_birth' => now()->subYears(10)]);

        $this->actingAs($adult)->post(route('family.propose'), ['member_id' => $minor->id])
            ->assertSessionHas('error');
        $this->assertNull($minor->detail->fresh()->family_id);

        GuardianLink::create(['guardian_user_id' => $adult->id, 'minor_user_id' => $minor->id, 'relationship' => 'parent']);

        // actingAs() re-uses this exact PHP object as the guard's user, so a
        // stale cached ->minors (empty, from the failed attempt above) would
        // never re-query — fresh() gets an instance with no cached relations.
        $this->actingAs($adult->fresh())->post(route('family.propose'), ['member_id' => $minor->id])->assertRedirect();

        $this->assertNotNull($minor->detail->fresh()->family_id);
        $this->assertSame($adult->detail->fresh()->family_id, $minor->detail->fresh()->family_id);
        $this->assertDatabaseCount('family_invites', 0);
    }

    public function test_a_minor_cannot_initiate_a_family(): void
    {
        $minor = $this->createMemberUser();
        $minor->detail->update(['date_of_birth' => now()->subYears(10)]);
        $other = $this->createMemberUser();

        $this->actingAs($minor)->post(route('family.propose'), ['member_id' => $other->id])
            ->assertSessionHas('error');
        $this->assertDatabaseCount('family_invites', 0);
    }

    public function test_can_act_for_is_false_across_two_unrelated_families(): void
    {
        $a = $this->createMemberUser();
        $b = $this->createMemberUser();
        $a->detail->update(['family_id' => $a->id]);
        $b->detail->update(['family_id' => $b->id]);

        $this->assertFalse($a->canActFor($b));
    }

    public function test_dues_preview_requires_a_family_link(): void
    {
        $a = $this->createMemberUser();
        $b = $this->createMemberUser();

        $this->actingAs($a)
            ->post(route('dues.calculate'), ['season_year' => '2027', 'status_id' => 1, 'target_user_id' => $b->id])
            ->assertForbidden();

        $familyId = $a->id;
        $a->detail->update(['family_id' => $familyId]);
        $b->detail->update(['family_id' => $familyId]);

        $this->actingAs($a)
            ->post(route('dues.calculate'), ['season_year' => '2027', 'status_id' => 1, 'target_user_id' => $b->id])
            ->assertOk();
    }

    public function test_medical_upload_requires_a_family_link(): void
    {
        Storage::fake('local');
        Mail::fake();
        $a = $this->createMemberUser();
        $b = $this->createMemberUser();

        $this->actingAs($a)
            ->post(route('profile.document.upload'), ['target_user_id' => $b->id, 'category' => 'medical', 'file' => UploadedFile::fake()->create('cert.pdf', 10)])
            ->assertForbidden();

        $familyId = $a->id;
        $a->detail->update(['family_id' => $familyId]);
        $b->detail->update(['family_id' => $familyId]);

        $this->actingAs($a)
            ->post(route('profile.document.upload'), ['target_user_id' => $b->id, 'category' => 'medical', 'file' => UploadedFile::fake()->create('cert.pdf', 10)])
            ->assertRedirect();

        $this->assertDatabaseHas('documents', ['user_id' => $b->id, 'category' => 'medical']);
    }

    public function test_leave_nulls_only_the_callers_own_family_id(): void
    {
        $a = $this->createMemberUser();
        $b = $this->createMemberUser();
        $familyId = $a->id;
        $a->detail->update(['family_id' => $familyId]);
        $b->detail->update(['family_id' => $familyId]);

        $this->actingAs($a)->post(route('family.leave'))->assertRedirect();

        $this->assertNull($a->detail->fresh()->family_id);
        $this->assertSame($familyId, $b->detail->fresh()->family_id);
    }
}
