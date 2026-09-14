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
class GdprErasureTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_a_socialite_only_account_can_erase_its_data_without_a_password(): void
    {
        $user = $this->socialiteOnlyUser();

        $this->actingAs($user)->post(route('gdpr.erasure.confirm'), [
            'confirm' => '1',
        ])->assertRedirect('/')->assertSessionDoesntHaveErrors();

        $this->assertSame('ERASED', $user->fresh()->detail->first_name);
    }

    public function test_a_regular_account_still_needs_its_password_to_erase(): void
    {
        $user = User::create([
            'username' => 'm'.uniqid(), 'primary_email' => 'm'.uniqid().'@test.com',
            'password' => 'Password1', 'role_id' => 2, 'status_id' => 1, 'email_verified_at' => now(),
        ]);
        $user->assignRole('member');
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'Regular', 'last_name' => 'User']);

        $this->actingAs($user)->post(route('gdpr.erasure.confirm'), [
            'confirm' => '1',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('password');

        $this->assertSame('Regular', $user->fresh()->detail->first_name);
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
}
