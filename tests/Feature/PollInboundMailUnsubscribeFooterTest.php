<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\PollInboundMail;
use App\Models\MailAliasDefinition;
use App\Models\MailListContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use ReflectionMethod;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Every recipient's own copy of a forwarded alias message carries an
 * explanatory footer; only a mail-list contact's copy also carries a
 * one-click unsubscribe link — a plain member recipient's copy must not,
 * since their alias membership is admin-controlled, not self-service. See
 * MailAliasService::resolveDefinition()/footerHtml() and the per-recipient
 * loop in PollInboundMail::processMessage().
 */
class PollInboundMailUnsubscribeFooterTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function processMessage(string $from, string $to, string $subject, string $body): void
    {
        $method = new ReflectionMethod(PollInboundMail::class, 'processMessage');
        $method->invoke(new PollInboundMail, $from, $to, $subject, $body);
    }

    public function test_a_contact_recipient_gets_an_unsubscribe_footer_but_a_member_does_not(): void
    {
        $bureau = $this->createBureauUser();
        $bureau->detail->update(['bureau_member' => true]);
        $contact = MailListContact::create(['email' => 'prospect@example.com']);
        MailAliasDefinition::create([
            'tags' => ['newsletter'], 'label' => 'Newsletter', 'criteria' => ['bureau'],
            'include_contact_ids' => [$contact->id], 'auth_level' => 'open',
        ]);

        $bodies = [];
        Mail::shouldReceive('html')->twice()->andReturnUsing(function (string $body, $callback) use (&$bodies) {
            $message = new class
            {
                public string $to = '';

                public function __call(string $name, array $args): self
                {
                    if ($name === 'to') {
                        $this->to = $args[0];
                    }

                    return $this;
                }
            };
            $callback($message);
            $bodies[$message->to] = $body;

        });
        Mail::shouldReceive('raw')->once();

        $this->processMessage('stranger@external.test', 'newsletter@example.com', 'Hello', 'Hi there');

        $this->assertStringContainsString('Unsubscribe', $bodies['prospect@example.com']);
        $this->assertStringContainsString('Se désabonner', $bodies['prospect@example.com']);
        $this->assertStringNotContainsString('Unsubscribe', $bodies[$bureau->primary_email]);
        $this->assertStringNotContainsString('Se désabonner', $bodies[$bureau->primary_email]);

        // Both copies still carry the always-on explanation, in both languages.
        $this->assertStringContainsString('member of', $bodies[$bureau->primary_email]);
        $this->assertStringContainsString('membre de', $bodies[$bureau->primary_email]);
        $this->assertStringContainsString('asked to stay informed', $bodies['prospect@example.com']);
        $this->assertStringContainsString('demandé à rester informé', $bodies['prospect@example.com']);
    }
}
