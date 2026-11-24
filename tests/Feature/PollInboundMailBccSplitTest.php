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
 * A single inbound message sent "To: alias-a, Bcc: alias-b" reaches Postfix
 * local delivery as two separate maildir files — one per envelope recipient
 * — both carrying the SAME visible "To:" header (Bcc never appears in
 * headers) but a distinct X-Original-To per copy. See
 * PollInboundMail::fetchMaildir() — it must key off X-Original-To (falling
 * back to To), not the shared To: header, or both copies resolve to the
 * same (visible) alias and the Bcc'd alias is silently dropped.
 */
class PollInboundMailBccSplitTest extends TestCase
{
    use RefreshDatabase;

    private string $maildir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->maildir = sys_get_temp_dir().'/pollinboundmail-bcc-'.Str::random(8);
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

    private function writeMaildirFile(string $originalTo, string $visibleTo): void
    {
        $raw = "Return-Path: <sender@example.test>\r\n"
            ."X-Original-To: {$originalTo}\r\n"
            ."Delivered-To: clubcep@localhost\r\n"
            ."From: Sender <sender@example.test>\r\n"
            ."To: {$visibleTo}\r\n"
            ."Subject: BCC split test\r\n"
            ."Content-Type: text/plain\r\n"
            ."\r\n"
            ."Body text.\r\n";

        file_put_contents($this->maildir.'/new/'.Str::random(16), $raw);
    }

    public function test_bcc_recipient_is_resolved_as_its_own_alias_not_duplicated_as_the_visible_one(): void
    {
        $contactA = MailListContact::create(['email' => 'a@example.test']);
        $contactB = MailListContact::create(['email' => 'b@example.test']);
        MailAliasDefinition::create([
            'tags' => ['aliasa'], 'label' => 'AliasA', 'criteria' => [],
            'include_contact_ids' => [$contactA->id], 'auth_level' => 'open', 'enabled' => true,
        ]);
        MailAliasDefinition::create([
            'tags' => ['aliasb'], 'label' => 'AliasB', 'criteria' => [],
            'include_contact_ids' => [$contactB->id], 'auth_level' => 'open', 'enabled' => true,
        ]);

        // Visible "To:" header is identical on both copies (aliasa) — only
        // X-Original-To differs, exactly as Postfix actually writes them.
        $this->writeMaildirFile('cep+aliasa@example.test', 'cep+aliasa@example.test');
        $this->writeMaildirFile('cep+aliasb@example.test', 'cep+aliasa@example.test');

        Mail::fake();
        (new PollInboundMail)->handle();

        $this->assertTrue(EmailLog::where('to_email', 'a@example.test')->exists(), 'AliasA recipient should have been forwarded to.');
        $this->assertTrue(EmailLog::where('to_email', 'b@example.test')->exists(), 'AliasB (Bcc) recipient should have been forwarded to, not dropped.');
        $this->assertSame(1, EmailLog::where('to_email', 'a@example.test')->count(), 'AliasA should not be processed twice.');
    }
}
