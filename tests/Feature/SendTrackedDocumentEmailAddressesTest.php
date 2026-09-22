<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendTrackedDocumentEmail;
use App\Models\DocumentDispatch;
use App\Models\DocumentDispatchRecipient;
use App\Models\LibraryFile;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Exercises SendTrackedDocumentEmail::addressesFor() directly via reflection
 * — the job's real handle() forwards to whatever Mail::default the
 * app-wide MailBalancer listener picks (see AppServiceProvider), which
 * bypasses the test mailer, so asserting on an actual send isn't reliable
 * here. The address-gathering logic is what this test guards.
 */
#[Group('p1')]
class SendTrackedDocumentEmailAddressesTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_includes_primary_and_verified_receive_mail_addresses_only(): void
    {
        $user = User::create([
            'username' => 'm'.uniqid(), 'primary_email' => 'primary@x.com',
            'password' => 'Password1', 'role_id' => 2, 'status_id' => 1, 'email_verified_at' => now(),
        ]);
        $user->assignRole('member');

        // Opted in: verified + "communicate here as well".
        UserEmail::create(['user_id' => $user->id, 'email' => 'Secondary.Opted-In@x.com', 'is_verified' => true, 'receive_mail' => true]);
        // Verified but opted out of mail.
        UserEmail::create(['user_id' => $user->id, 'email' => 'verified-no-mail@x.com', 'is_verified' => true, 'receive_mail' => false]);
        // Opted in but never verified — must not receive club mail at an unconfirmed address.
        UserEmail::create(['user_id' => $user->id, 'email' => 'unverified-opted-in@x.com', 'is_verified' => false, 'receive_mail' => true]);

        $file = LibraryFile::create([
            'filename' => 'f.pdf', 'original_name' => 'F.pdf', 'path' => 'lib/f.pdf',
            'mime_type' => 'application/pdf', 'size' => 10, 'visibility' => 'members',
            'uploaded_by' => $user->id,
        ]);
        $dispatch = DocumentDispatch::create(['library_file_id' => $file->id, 'subject' => 'x', 'created_by' => null]);
        $recipient = DocumentDispatchRecipient::create([
            'dispatch_id' => $dispatch->id, 'user_id' => $user->id,
            'email' => 'primary@x.com', 'token' => str_repeat('a', 40),
        ])->load('user.emails');

        $job = new SendTrackedDocumentEmail($recipient->id);
        $method = new \ReflectionMethod(SendTrackedDocumentEmail::class, 'addressesFor');
        $method->setAccessible(true);
        $addresses = $method->invoke($job, $recipient);

        sort($addresses);
        $this->assertSame(['primary@x.com', 'secondary.opted-in@x.com'], $addresses);
    }

    /**
     * A tracked document is sent from a real, monitored inbox (CLUB_CONTACT_EMAIL)
     * rather than the app-wide MAIL_FROM_ADDRESS default, which is an alias
     * mailbox meant for inbound routing, not one a recipient's reply would reach.
     */
    public function test_sends_from_the_club_contact_address_not_the_app_default(): void
    {
        config(['club.contact_email' => 'info@clubcep.eu', 'mail.from.name' => 'CEP']);

        $job = new SendTrackedDocumentEmail(1);
        $method = new \ReflectionMethod(SendTrackedDocumentEmail::class, 'fromAddress');
        $method->setAccessible(true);

        $this->assertSame(['info@clubcep.eu', 'CEP'], $method->invoke($job));
    }
}
