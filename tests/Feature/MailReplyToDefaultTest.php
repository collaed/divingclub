<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ThemeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Covers the admin-configurable default Reply-To (AppServiceProvider's
 * MessageSending listener) — applied only when a send didn't already set
 * its own, so e.g. PollInboundMail's reply-to-the-original-sender keeps
 * working untouched.
 */
class MailReplyToDefaultTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'array']);
        app('mail.manager')->purge();
    }

    private function sentMessages()
    {
        return Mail::mailer('array')->getSymfonyTransport()->messages();
    }

    public function test_default_reply_to_is_applied_when_none_is_set(): void
    {
        ThemeSetting::set('mail_reply_to', 'reply@clubcep.eu');

        Mail::html('hi', fn ($m) => $m->to('x@example.com')->subject('s'));

        $message = $this->sentMessages()->first()->getOriginalMessage();
        $this->assertSame('reply@clubcep.eu', $message->getReplyTo()[0]->getAddress());
    }

    public function test_explicit_reply_to_is_not_overridden(): void
    {
        ThemeSetting::set('mail_reply_to', 'reply@clubcep.eu');

        Mail::html('hi', fn ($m) => $m->to('x@example.com')->replyTo('sender@original.test')->subject('s'));

        $message = $this->sentMessages()->first()->getOriginalMessage();
        $this->assertSame('sender@original.test', $message->getReplyTo()[0]->getAddress());
    }

    public function test_no_reply_to_is_added_when_not_configured(): void
    {
        Mail::html('hi', fn ($m) => $m->to('x@example.com')->subject('s'));

        $message = $this->sentMessages()->first()->getOriginalMessage();
        $this->assertSame([], $message->getReplyTo());
    }
}
