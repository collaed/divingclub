<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

#[Group('p1')]
class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_a_socialite_only_account_can_set_a_first_password_without_a_current_one(): void
    {
        $user = $this->socialiteOnlyUser();

        $this->actingAs($user)->post(route('profile.update.password'), [
            'password' => 'BrandNewPass1',
            'password_confirmation' => 'BrandNewPass1',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertTrue(\Hash::check('BrandNewPass1', $user->fresh()->password));
    }

    public function test_a_regular_account_still_needs_its_current_password(): void
    {
        $user = $this->memberWithPassword('Password1');

        $this->actingAs($user)->post(route('profile.update.password'), [
            'current_password' => 'wrong-password',
            'password' => 'BrandNewPass1',
            'password_confirmation' => 'BrandNewPass1',
        ])->assertSessionHasErrors('current_password');
    }

    public function test_a_regular_account_can_change_its_password_with_the_right_current_one(): void
    {
        $user = $this->memberWithPassword('Password1');

        $this->actingAs($user)->post(route('profile.update.password'), [
            'current_password' => 'Password1',
            'password' => 'BrandNewPass1',
            'password_confirmation' => 'BrandNewPass1',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertTrue(\Hash::check('BrandNewPass1', $user->fresh()->password));
    }

    private function socialiteOnlyUser(): User
    {
        $user = User::create([
            'username' => 'sso'.uniqid(),
            'primary_email' => 'sso'.uniqid().'@test.com',
            'password' => null,
            'role_id' => 2,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('member');
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'Social', 'last_name' => 'User']);

        return $user;
    }

    private function memberWithPassword(string $password): User
    {
        $user = User::create([
            'username' => 'm'.uniqid(),
            'primary_email' => 'm'.uniqid().'@test.com',
            'password' => $password,
            'role_id' => 2,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('member');
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'Regular', 'last_name' => 'User']);

        return $user;
    }
}
