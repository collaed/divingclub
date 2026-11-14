<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\PollInboundMail;
use App\Models\EmailLog;
use App\Models\MailForwardingRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use ReflectionMethod;
use Tests\TestCase;

class InboundMailContactRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function processMessage(string $from, string $to, string $subject, string $body): void
    {
        $method = new ReflectionMethod(PollInboundMail::class, 'processMessage');
        $method->invoke(new PollInboundMail, $from, $to, $subject, $body);
    }

    public function test_mail_to_an_address_with_a_forwarding_rule_is_forwarded_unconditionally(): void
    {
        MailForwardingRule::create(['from_address' => 'info@example.com', 'to_addresses' => 'club@gmail.example']);

        $message = $this->createMock(Message::class);
        $message->expects($this->once())->method('to')->with('club@gmail.example')->willReturnSelf();
        $message->expects($this->once())->method('replyTo')->with('random.stranger@external.test')->willReturnSelf();
        $message->expects($this->once())->method('subject')->with('Question about joining')->willReturnSelf();

        Mail::shouldReceive('html')
            ->once()
            ->withArgs(function ($html, $callback) use ($message): bool {
                $callback($message);

                return true;
            });

        // Not a club member — would be rejected by the normal alias path.
        $this->processMessage('random.stranger@external.test', 'info@example.com', 'Question about joining', 'Hi, how do I join?');

        $log = EmailLog::where('to_email', 'info@example.com')->first();
        $this->assertNotNull($log);
        $this->assertSame('forwarded', $log->status);
        $this->assertSame('inbound', $log->direction);
        $this->assertTrue((bool) $log->authorized);
    }

    public function test_a_rule_can_forward_to_more_than_one_target(): void
    {
        MailForwardingRule::create(['from_address' => 'info@example.com', 'to_addresses' => 'a@example.com, b@example.com']);

        Mail::shouldReceive('html')->twice();

        $this->processMessage('random.stranger@external.test', 'info@example.com', 'Hi', 'Hi there');

        $log = EmailLog::where('to_email', 'info@example.com')->first();
        $this->assertSame('forwarded', $log->status);
        $this->assertSame('Redirected to a@example.com, b@example.com', $log->error);
    }

    public function test_a_disabled_rule_is_ignored(): void
    {
        MailForwardingRule::create(['from_address' => 'info@example.com', 'to_addresses' => 'club@gmail.example', 'enabled' => false]);

        Mail::shouldReceive('html')->never();

        $this->processMessage('random.stranger@external.test', 'info@example.com', 'Hello', 'Hi there');

        $log = EmailLog::where('to_email', 'info@example.com')->first();
        $this->assertNotNull($log);
        $this->assertSame('rejected', $log->status);
    }

    public function test_other_addresses_are_unaffected_by_a_forwarding_rule(): void
    {
        MailForwardingRule::create(['from_address' => 'info@example.com', 'to_addresses' => 'club@gmail.example']);

        Mail::shouldReceive('html')->never();

        // Unknown alias, unauthorized sender — falls through to the normal
        // "unauthorized sender" rejection path, untouched by the rule above.
        $this->processMessage('random.stranger@external.test', 'bureau@example.com', 'Hello', 'Hi there');

        $log = EmailLog::where('to_email', 'bureau@example.com')->first();
        $this->assertNotNull($log);
        $this->assertSame('rejected', $log->status);
    }

    public function test_forwarding_is_a_noop_when_no_rule_matches(): void
    {
        Mail::shouldReceive('html')->never();

        $this->processMessage('random.stranger@external.test', 'info@example.com', 'Hello', 'Hi there');

        $log = EmailLog::where('to_email', 'info@example.com')->first();
        $this->assertNotNull($log);
        $this->assertSame('rejected', $log->status);
    }
}
