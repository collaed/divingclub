<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

#[Group('p1')]
class ProfileEmailVerificationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_adding_an_email_sends_a_verification_link(): void
    {
        Mail::fake();
        $user = $this->member();

        $this->actingAs($user)->post(route('profile.email.add'), ['email' => 'second@test.com'])->assertRedirect();

        $email = UserEmail::where('email', 'second@test.com')->firstOrFail();
        $this->assertFalse($email->is_verified);
        $this->assertNotNull($email->verification_token);
    }

    public function test_visiting_the_verification_link_marks_the_email_verified(): void
    {
        $user = $this->member();
        $email = UserEmail::create([
            'user_id' => $user->id, 'email' => 'second@test.com',
            'is_verified' => false, 'verification_token' => 'tok123',
        ]);

        $this->get(route('profile.email.verify', 'tok123'))->assertRedirect();

        $email->refresh();
        $this->assertTrue($email->is_verified);
        $this->assertNull($email->verification_token);
    }

    public function test_an_unverified_email_cannot_be_set_as_primary_until_the_link_is_confirmed(): void
    {
        $user = $this->member();
        $email = UserEmail::create([
            'user_id' => $user->id, 'email' => 'second@test.com',
            'is_verified' => false, 'verification_token' => 'tok456',
        ]);

        $this->actingAs($user)->post(route('profile.email.primary', $email))->assertSessionHas('error');
        $this->assertFalse($email->fresh()->is_primary);

        $this->get(route('profile.email.verify', 'tok456'));

        $this->actingAs($user)->post(route('profile.email.primary', $email))->assertSessionHas('success');
        $this->assertTrue($email->fresh()->is_primary);
    }

    public function test_a_bad_token_does_not_error_and_verifies_nothing(): void
    {
        $this->get(route('profile.email.verify', 'not-a-real-token'))->assertRedirect();
        $this->assertSame(0, UserEmail::where('is_verified', true)->count());
    }

    public function test_resend_issues_a_fresh_token(): void
    {
        Mail::fake();
        $user = $this->member();
        $email = UserEmail::create([
            'user_id' => $user->id, 'email' => 'second@test.com',
            'is_verified' => false, 'verification_token' => 'old-token',
        ]);

        $this->actingAs($user)->post(route('profile.email.resend', $email))->assertRedirect();

        $this->assertNotSame('old-token', $email->fresh()->verification_token);
    }

    public function test_resend_refuses_an_already_verified_email(): void
    {
        $user = $this->member();
        $email = UserEmail::create(['user_id' => $user->id, 'email' => 'second@test.com', 'is_verified' => true]);

        $this->actingAs($user)->post(route('profile.email.resend', $email))->assertSessionHas('error');
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole('member');
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'User']);

        return $user;
    }
}
