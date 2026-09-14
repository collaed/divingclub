<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

#[Group('p1')]
class ProfileVisibilityTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_an_unconfirmed_viewer_cannot_see_another_members_profile(): void
    {
        $viewer = $this->unconfirmedUser();
        $target = $this->createMemberUser();

        $this->actingAs($viewer)->get(route('members.profile', $target))->assertForbidden();
    }

    public function test_an_unconfirmed_viewer_can_still_see_their_own_profile(): void
    {
        $viewer = $this->unconfirmedUser();

        $this->actingAs($viewer)->get(route('profile.show'))->assertOk();
    }

    public function test_an_unconfirmed_viewer_cannot_browse_the_directory_or_trombinoscope(): void
    {
        $viewer = $this->unconfirmedUser();

        $this->actingAs($viewer)->get(route('members.directory'))->assertForbidden();
        $this->actingAs($viewer)->get(route('members.trombinoscope'))->assertForbidden();
    }

    public function test_an_active_member_sees_licence_and_registrations_but_not_private_medical_or_equipment(): void
    {
        $viewer = $this->createMemberUser();
        $target = $this->createMemberUser();

        $response = $this->actingAs($viewer)->get(route('members.profile', $target))->assertOk();

        $response->assertSee(__('Licence Overview'));
        $response->assertSee(__('Registrations'));
        $response->assertDontSee(__('Private Info'));
        $response->assertDontSee(__('Medical Cert'));
        $response->assertDontSee(__('Equipment on Loan'));
    }

    public function test_an_instructor_sees_medical_and_equipment_but_not_private_info(): void
    {
        $viewer = $this->instructorUser();
        $target = $this->createMemberUser();

        $response = $this->actingAs($viewer)->get(route('members.profile', $target))->assertOk();

        $response->assertSee(__('Medical Cert'));
        $response->assertSee(__('Equipment on Loan'));
        $response->assertSee(__('Licence Overview'));
        $response->assertDontSee(__('Private Info'));
    }

    public function test_bureau_sees_every_tab_including_private_info(): void
    {
        $viewer = $this->createBureauUser();
        $target = $this->createMemberUser();

        $response = $this->actingAs($viewer)->get(route('members.profile', $target))->assertOk();

        $response->assertSee(__('Private Info'));
        $response->assertSee(__('Medical Cert'));
        $response->assertSee(__('Equipment on Loan'));
    }

    public function test_bureau_can_manage_a_members_email_addresses_but_an_active_member_cannot(): void
    {
        $target = $this->createMemberUser();
        UserEmail::create(['user_id' => $target->id, 'email' => 'secondary@test.com', 'is_verified' => true, 'receive_mail' => true]);

        $bureau = $this->createBureauUser();
        $bureauResponse = $this->actingAs($bureau)->get(route('members.profile', $target))->assertOk();
        $bureauResponse->assertSee(__('Set Primary'));

        $otherMember = $this->createMemberUser();
        $memberResponse = $this->actingAs($otherMember)->get(route('members.profile', $target))->assertOk();
        $memberResponse->assertDontSee(__('Set Primary'));
    }

    private function unconfirmedUser(): User
    {
        $user = User::create([
            'username' => 'pending'.uniqid(),
            'primary_email' => 'pending'.uniqid().'@test.com',
            'password' => 'Password1',
            'role_id' => 2,
            'status_id' => null,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('member');
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'Pending', 'last_name' => 'User']);

        return $user;
    }

    private function instructorUser(): User
    {
        $user = User::create([
            'username' => 'instructor'.uniqid(),
            'primary_email' => 'instructor'.uniqid().'@test.com',
            'password' => 'Password1',
            'role_id' => 2,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('instructor');
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'Instructor', 'last_name' => 'User']);

        return $user;
    }
}
