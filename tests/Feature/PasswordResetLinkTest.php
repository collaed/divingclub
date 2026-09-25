<?php

namespace Tests\Feature;

use App\Models\ThemeSetting;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
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

    /**
     * A French sender name with an English "Reset Password Notification" body,
     * branded as the generic "DivingClub" app name, is exactly the mismatch
     * spam filters flag as phishing — see CHANGELOG. The notification is now
     * sent in the member's own preferred locale, defaulting to French.
     */
    public function test_reset_notification_is_sent_in_the_members_preferred_locale(): void
    {
        Notification::fake();

        $frenchDefault = $this->member('no-locale@example.com');
        $english = $this->member('english@example.com');
        $english->update(['preferred_locale' => 'en']);

        Password::sendResetLink(['email' => 'no-locale@example.com']);
        Password::sendResetLink(['email' => 'english@example.com']);

        Notification::assertSentOnDemand(ResetPassword::class, fn ($n, $c, $notifiable) => in_array('no-locale@example.com', (array) ($notifiable->routes['mail'] ?? []), true) && $n->locale === 'fr');
        Notification::assertSentOnDemand(ResetPassword::class, fn ($n, $c, $notifiable) => in_array('english@example.com', (array) ($notifiable->routes['mail'] ?? []), true) && $n->locale === 'en');
    }

    public function test_the_rendered_email_is_branded_with_the_clubs_own_name_and_address_not_the_generic_app_name(): void
    {
        ThemeSetting::set('club_full_name', 'Club Européen de Plongée');
        ThemeSetting::set('club_address', '10, rue Benjamin Franklin / L-1540 Luxembourg');
        $user = $this->member('brand-check@example.com');
        $originalLocale = app()->getLocale();

        // Mirrors what the real send path does: the channel manager wraps
        // toMail() in withLocale() when a locale is set, so its Lang::get()
        // calls resolve in that locale — a direct toMail() call here doesn't
        // get that for free, hence setting it explicitly.
        app()->setLocale('fr');
        $message = (new ResetPassword('some-token'))->toMail($user);
        $rendered = app(Markdown::class)->render('notifications::email', $message->toArray());
        app()->setLocale($originalLocale);

        $this->assertStringContainsString('Club Européen de Plongée', $rendered);
        $this->assertStringContainsString('10, rue Benjamin Franklin', $rendered);
        $this->assertStringNotContainsString('DivingClub', $rendered);
        // The subject line itself is a mail header, not part of the rendered
        // body — checking a body line the French translation feeds instead.
        $this->assertStringContainsString('Vous recevez cet e-mail car nous avons reçu une demande', $rendered);
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
