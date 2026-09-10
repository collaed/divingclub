<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class PasswordResetLinkTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function member(string $primary): User
    {
        $user = User::create([
            'username' => 'm'.uniqid(),
            'primary_email' => $primary,
            'password' => 'Password1',
            'role_id' => 2,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('member');

        return $user;
    }

    public function test_reset_link_goes_to_every_verified_mail_enabled_address_plus_primary(): void
    {
        Notification::fake();

        $user = $this->member('alice@example.com');
        UserEmail::create(['user_id' => $user->id, 'email' => 'alice.work@example.com', 'is_verified' => true, 'receive_mail' => true]);
        UserEmail::create(['user_id' => $user->id, 'email' => 'alice.optout@example.com', 'is_verified' => true, 'receive_mail' => false]);
        UserEmail::create(['user_id' => $user->id, 'email' => 'alice.new@example.com', 'is_verified' => false, 'receive_mail' => true]);

        Password::sendResetLink(['email' => 'alice@example.com']);

        Notification::assertCount(2);
        foreach (['alice@example.com', 'alice.work@example.com'] as $address) {
            Notification::assertSentOnDemand(
                ResetPassword::class,
                fn ($notification, $channels, $notifiable) => in_array($address, (array) ($notifiable->routes['mail'] ?? []), true)
            );
        }
    }

    public function test_a_verified_secondary_address_can_request_the_reset(): void
    {
        Notification::fake();

        $user = $this->member('bob@example.com');
        UserEmail::create(['user_id' => $user->id, 'email' => 'bob.alt@example.com', 'is_verified' => true, 'receive_mail' => true]);

        // Member types their secondary address into the forgot-password form.
        Password::sendResetLink(['email' => 'bob.alt@example.com']);

        Notification::assertCount(2);
    }

    public function test_admin_send_reset_button_fans_out_to_all_verified_addresses(): void
    {
        Notification::fake();

        $admin = $this->createBureauUser();
        $target = $this->member('carol@example.com');
        UserEmail::create(['user_id' => $target->id, 'email' => 'carol.alt@example.com', 'is_verified' => true, 'receive_mail' => true]);

        $this->actingAs($admin)
            ->post(route('admin.send-reset', $target))
            ->assertRedirect()
            ->assertSessionHas('success');

        Notification::assertCount(2);
    }

    public function test_outgoing_reset_mail_is_written_to_email_log(): void
    {
        // No Notification::fake() — let it flow through the array mailer so the
        // MessageSent listener records a row.
        $this->member('dave@example.com');

        Password::sendResetLink(['email' => 'dave@example.com']);

        $this->assertDatabaseHas('email_log', [
            'to_email' => 'dave@example.com',
            'direction' => 'outbound',
            'status' => 'sent',
        ]);
    }
}
