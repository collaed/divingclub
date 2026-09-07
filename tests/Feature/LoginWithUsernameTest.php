<?php

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

#[Group('p1')]
class LoginWithUsernameTest extends TestCase
{
    use RefreshDatabase;

    private const PW = 'Password1!';

    protected function setUp(): void
    {
        parent::setUp();
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'Member', 'slug' => 'member']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'active']);
        SpatieRole::findOrCreate('member', 'web');
    }

    private function member(array $attrs = []): User
    {
        $user = User::create(array_merge([
            'username' => null,
            'primary_email' => 'primary'.uniqid().'@example.com',
            'password' => self::PW,
            'role_id' => 2,
            'status_id' => 1,
            'email_verified_at' => now(),
        ], $attrs));
        $user->assignRole('member');
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'T', 'last_name' => 'U']);

        return $user;
    }

    public function test_login_with_primary_email_still_works(): void
    {
        $user = $this->member();

        $this->post('/login', ['email' => strtoupper($user->primary_email), 'password' => self::PW])
            ->assertRedirect(route('profile.show'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_legacy_username_case_insensitive(): void
    {
        $user = $this->member(['username' => 'eric.richard']);

        $this->post('/login', ['email' => 'ERIC.RICHARD', 'password' => self::PW])
            ->assertRedirect(route('profile.show'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_legacy_username_containing_spaces(): void
    {
        $user = $this->member(['username' => 'Michel B']);

        $this->post('/login', ['email' => '  michel b  ', 'password' => self::PW])
            ->assertRedirect(route('profile.show'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_verified_secondary_email(): void
    {
        $user = $this->member();
        UserEmail::create(['user_id' => $user->id, 'email' => 'Alt.Address@Example.com', 'is_verified' => true]);

        $this->post('/login', ['email' => 'alt.address@example.com', 'password' => self::PW])
            ->assertRedirect(route('profile.show'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_unverified_secondary_email_is_rejected(): void
    {
        $user = $this->member();
        UserEmail::create(['user_id' => $user->id, 'email' => 'pending@example.com', 'is_verified' => false]);

        $this->from('/login')->post('/login', ['email' => 'pending@example.com', 'password' => self::PW])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_username_login_with_wrong_password_is_rejected(): void
    {
        $this->member(['username' => 'joe.bloggs']);

        $this->from('/login')->post('/login', ['email' => 'joe.bloggs', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_passwordless_user_cannot_login_by_username(): void
    {
        $user = $this->member(['username' => 'oauth.only']);
        DB::table('users')->where('id', $user->id)->update(['password' => null]);

        $this->from('/login')->post('/login', ['email' => 'oauth.only', 'password' => 'anything'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_unknown_identifier_is_rejected(): void
    {
        $this->from('/login')->post('/login', ['email' => 'nobody-here', 'password' => 'x'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_ambiguous_case_variant_username_fails_closed(): void
    {
        // The unique index is case-sensitive, so both can exist.
        $this->member(['username' => 'Michel B']);
        $this->member(['username' => 'michel b']);

        $this->from('/login')->post('/login', ['email' => 'michel b', 'password' => self::PW])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_plus_addressed_primary_email_logs_in_but_the_base_address_does_not(): void
    {
        $user = $this->member(['primary_email' => 'diver+club@example.com']);

        $this->post('/login', ['email' => 'DIVER+CLUB@example.com', 'password' => self::PW])
            ->assertRedirect(route('profile.show'));
        $this->assertAuthenticatedAs($user);

        auth()->logout();

        $this->from('/login')->post('/login', ['email' => 'diver@example.com', 'password' => self::PW])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_malformed_email_shaped_identifier_is_rejected(): void
    {
        $this->member(['primary_email' => 'real@example.com']);

        $this->from('/login')->post('/login', ['email' => 'not an @ email', 'password' => self::PW])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
