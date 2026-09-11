<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CertificationLevel;
use App\Models\Federation;
use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Every certification action (add/update/set-primary/remove) always operated
 * on auth()->user() regardless of which profile the form was submitted
 * from — a bureau member adding a certification to someone else's profile
 * silently added it to their own instead. See ProfileCertificationController.
 */
class ProfileCertificationTargetTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private function member(string $email): User
    {
        $user = User::create([
            'username' => 'm'.uniqid(),
            'primary_email' => $email,
            'password' => 'Password1',
            'role_id' => 2,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('member');
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'T', 'last_name' => 'U']);

        return $user;
    }

    private function certLevel(): CertificationLevel
    {
        $fed = Federation::create(['acronym' => 'FFESSM', 'full_name' => 'FFESSM', 'visibility' => 'active']);

        return CertificationLevel::create(['federation_id' => $fed->id, 'code' => 'ANTEOR', 'name' => 'Anteor', 'category' => 'specialty', 'rank' => 230]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_bureau_adding_a_certification_from_another_members_profile_targets_that_member(): void
    {
        $bureau = $this->createBureauUser();
        $target = $this->member('target@example.com');
        $cert = $this->certLevel();

        $this->actingAs($bureau)->post(route('profile.cert.add'), [
            'certification_level_id' => $cert->id,
            'target_user_id' => $target->id,
        ])->assertRedirect();

        $this->assertTrue($target->fresh()->certificationLevels->contains($cert->id));
        $this->assertFalse($bureau->fresh()->certificationLevels->contains($cert->id));
    }

    public function test_bureau_can_update_remove_and_set_primary_on_another_members_certification(): void
    {
        $bureau = $this->createBureauUser();
        $target = $this->member('target2@example.com');
        $cert = $this->certLevel();
        $target->certificationLevels()->attach($cert->id, ['obtained_date' => null, 'display_priority' => 0]);

        $this->actingAs($bureau)->put(route('profile.cert.update', $cert->id), [
            'obtained_date' => '2020-01-01',
            'target_user_id' => $target->id,
        ])->assertRedirect();
        $this->assertSame('2020-01-01', $target->certificationLevels()->find($cert->id)->pivot->obtained_date);

        $this->actingAs($bureau)->post(route('profile.cert.primary', $cert->id), [
            'target_user_id' => $target->id,
        ])->assertRedirect();
        $this->assertTrue((bool) $target->certificationLevels()->find($cert->id)->pivot->is_primary);

        $this->actingAs($bureau)->delete(route('profile.cert.remove', $cert->id), [
            'target_user_id' => $target->id,
        ])->assertRedirect();
        $this->assertFalse($target->fresh()->certificationLevels->contains($cert->id));
    }

    public function test_a_regular_member_cannot_add_a_certification_to_someone_else(): void
    {
        $member = $this->member('me@example.com');
        $other = $this->member('other@example.com');
        $cert = $this->certLevel();

        $this->actingAs($member)->post(route('profile.cert.add'), [
            'certification_level_id' => $cert->id,
            'target_user_id' => $other->id,
        ])->assertForbidden();

        $this->assertFalse($other->fresh()->certificationLevels->contains($cert->id));
    }

    public function test_without_target_user_id_it_still_applies_to_self(): void
    {
        $member = $this->member('self@example.com');
        $cert = $this->certLevel();

        $this->actingAs($member)->post(route('profile.cert.add'), [
            'certification_level_id' => $cert->id,
        ])->assertRedirect();

        $this->assertTrue($member->fresh()->certificationLevels->contains($cert->id));
    }
}
