<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

#[Group('p1')]
class SocialAuthCreateNewTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_choosing_im_new_creates_an_account_pending_bureau_approval(): void
    {
        $this->withSession(['pending_social_new' => [
            'provider' => 'google',
            'provider_user_id' => 'g-12345',
            'email' => 'new.diver@example.com',
            'name' => 'New Diver',
            'token' => encrypt('fake-token'),
            'refresh_token' => encrypt('fake-refresh-token'),
        ]])->post(route('auth.social.create-new'))->assertRedirect(route('profile.show'));

        $user = User::where('primary_email', 'new.diver@example.com')->firstOrFail();
        $this->assertNotNull($user->role_id);
        $this->assertTrue($user->hasRole('member'));
        // No member_statuses row means "pending bureau approval" — see
        // DashboardController::new_members_unconfirmed.
        $this->assertNull($user->status_id);
        $this->assertAuthenticatedAs($user);
    }
}
