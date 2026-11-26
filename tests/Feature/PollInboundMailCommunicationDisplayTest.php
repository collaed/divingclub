<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\PollInboundMail;
use App\Models\EmailLog;
use App\Models\MailAliasDefinition;
use App\Models\MailListContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Two bugs reported from a live prod test of event-communication forwarding:
 *
 * 1. One inbound message produced TWO entries on the event's Communications
 *    panel — EmailLog::threadKey() grouped by created_at at second
 *    precision, and a batch of per-recipient network sends (MailBalancer)
 *    is slow enough to straddle a second boundary. Fixed by giving every
 *    row from the same inbound message a shared batch_id to thread on.
 * 2. The body shown was raw/double-escaped HTML source (e.g. literal
 *    "<div dir="ltr">...&#39;...") instead of readable text — the stored
 *    body was the chosen MIME part's HTML source (needed verbatim for
 *    Mail::html() to forward it correctly), then Blade's `{{ }}` escaped
 *    it a second time for display. Fixed by converting to plain text
 *    before storing (PollInboundMail::toDisplayText()), independent of
 *    what's actually forwarded.
 */
class PollInboundMailCommunicationDisplayTest extends TestCase
{
    use RefreshDatabase;

    private string $maildir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->maildir = sys_get_temp_dir().'/pollinboundmail-display-'.Str::random(8);
        mkdir($this->maildir.'/new', 0755, true);
        mkdir($this->maildir.'/cur', 0755, true);
        config([
            'services.inbound_mail.enabled' => true,
            'services.inbound_mail.mode' => 'maildir',
            'services.inbound_mail.maildir' => $this->maildir,
        ]);
    }

    protected function tearDown(): void
    {
        foreach (['new', 'cur'] as $dir) {
            foreach (glob("{$this->maildir}/{$dir}/*") ?: [] as $f) {
                unlink($f);
            }
            @rmdir("{$this->maildir}/{$dir}");
        }
        @rmdir($this->maildir);
        parent::tearDown();
    }

    private function writeHtmlMaildirFile(string $to): void
    {
        $html = '<div dir="ltr"><div>Bonjour,</div><div><br></div>'
            .'<div>J&#39;ai cr&eacute;&eacute; cet event...</div></div>';

        $raw = "From: Sender <sender@example.test>\r\n"
            ."To: {$to}\r\n"
            ."Subject: Html body test\r\n"
            ."Content-Type: text/html; charset=UTF-8\r\n"
            ."\r\n"
            .$html;

        file_put_contents($this->maildir.'/new/'.Str::random(16), $raw);
    }

    private function makeAliasWithContacts(string $tag, array $emails): void
    {
        $ids = array_map(fn (string $email) => MailListContact::create(['email' => $email])->id, $emails);
        MailAliasDefinition::create([
            'tags' => [$tag], 'label' => $tag, 'criteria' => [],
            'include_contact_ids' => $ids, 'auth_level' => 'open', 'enabled' => true,
        ]);
    }

    public function test_forwarded_copies_of_one_message_share_a_batch_id_and_thread_together(): void
    {
        $this->makeAliasWithContacts('multi', ['r1@example.test', 'r2@example.test', 'r3@example.test']);
        $this->writeHtmlMaildirFile('cep+multi@example.test');

        Mail::fake();
        (new PollInboundMail)->handle();

        $logs = EmailLog::where('subject', 'like', '%Html body test%')->get();
        $this->assertCount(3, $logs);

        $batchIds = $logs->pluck('batch_id')->unique();
        $this->assertCount(1, $batchIds, 'All copies of one inbound message should share a single batch_id.');
        $this->assertNotNull($batchIds->first());

        // Simulate the per-recipient sends straddling a second boundary —
        // this alone used to split one message into two Communications.
        $logs->last()->forceFill(['created_at' => $logs->last()->created_at->addSecond()])->save();

        $threadKeys = $logs->fresh()->pluck(fn (EmailLog $l) => $l->threadKey());
        $this->assertCount(1, $threadKeys->unique(), 'Rows must still thread together despite differing created_at seconds.');
    }

    public function test_the_stored_body_is_plain_readable_text_not_raw_or_double_escaped_html(): void
    {
        $this->makeAliasWithContacts('single', ['r1@example.test']);
        $this->writeHtmlMaildirFile('cep+single@example.test');

        Mail::fake();
        (new PollInboundMail)->handle();

        $log = EmailLog::where('subject', 'like', '%Html body test%')->firstOrFail();

        $this->assertStringNotContainsString('<div', $log->body);
        $this->assertStringNotContainsString('&#39;', $log->body);
        $this->assertStringNotContainsString('&amp;', $log->body);
        $this->assertStringContainsString("J'ai créé cet event", $log->body);
    }
}
