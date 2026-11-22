<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\MailAliasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * training_enrollments mixes free text (anything typed in over time) with a
 * controlled self-enrollment vocabulary: N1/N2/N3 mutually exclusive,
 * RIFAP/NITROX/QUALIFS independent of each other and of the level — the
 * controlled tokens are always derived fresh from training_level/
 * training_flags, never read back from the free-text box.
 */
class ProfileDivingTrainingEnrollmentTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_a_level_and_independent_flags_can_be_set_together(): void
    {
        $user = $this->createMemberUser();

        $this->actingAs($user)->post(route('profile.update.diving'), [
            'training_level' => 'N2',
            'training_flags' => ['RIFAP', 'NITROX'],
        ])->assertRedirect();

        $enrolled = $user->detail->fresh()->training_enrollments;
        sort($enrolled);
        $this->assertSame(['N2', 'NITROX', 'RIFAP'], $enrolled);
    }

    public function test_an_invalid_level_is_rejected(): void
    {
        $user = $this->createMemberUser();

        $this->actingAs($user)->post(route('profile.update.diving'), ['training_level' => 'N9'])
            ->assertSessionHasErrors('training_level');
    }

    public function test_free_text_notes_survive_alongside_the_structured_choice(): void
    {
        $user = $this->createMemberUser();

        $this->actingAs($user)->post(route('profile.update.diving'), [
            'training_level' => 'N1',
            'training_enrollments' => 'MEF1, N4 theory',
        ])->assertRedirect();

        $enrolled = $user->detail->fresh()->training_enrollments;
        sort($enrolled);
        $this->assertSame(['MEF1', 'N1', 'N4 theory'], $enrolled);
    }

    public function test_changing_the_level_does_not_leave_the_old_one_behind(): void
    {
        $user = $this->createMemberUser();

        $this->actingAs($user)->post(route('profile.update.diving'), ['training_level' => 'N1']);
        $this->actingAs($user)->post(route('profile.update.diving'), ['training_level' => 'N2']);

        $this->assertSame(['N2'], $user->detail->fresh()->training_enrollments);
    }

    public function test_rifap_nitrox_qualifs_aliases_resolve_self_enrolled_members(): void
    {
        $user = $this->createMemberUser();
        $this->actingAs($user)->post(route('profile.update.diving'), ['training_flags' => ['RIFAP']]);

        $resolved = MailAliasService::resolve('members.rifap@clubcep.eu');

        $this->assertNotNull($resolved);
        $this->assertContains($user->primary_email, $resolved['emails']);
        $this->assertSame([], MailAliasService::resolve('members.nitrox@clubcep.eu')['emails']);
    }
}
