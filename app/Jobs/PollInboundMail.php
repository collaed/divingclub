<?php

namespace App\Jobs;

use App\Models\EmailLog;
use App\Models\User;
use App\Services\MailAliasService;
use App\Services\ScheduleHeartbeat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Option A: Poll an IMAP mailbox for inbound alias emails.
 *
 * Checks a dedicated mailbox (e.g. sas@clubcep.eu or members@ecb.pm)
 * for unread messages, resolves aliases from the To/Subject, and forwards.
 *
 * Configure in .env:
 *   INBOUND_MAIL_ENABLED=true
 *   INBOUND_IMAP_HOST=mail.ecb.pm
 *   INBOUND_IMAP_PORT=993
 *   INBOUND_IMAP_USER=members@ecb.pm
 *   INBOUND_IMAP_PASSWORD=secret
 *   INBOUND_IMAP_ENCRYPTION=ssl
 */
class PollInboundMail implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        if (! config('services.inbound_mail.enabled')) {
            ScheduleHeartbeat::beat('inbound-mail', 'Disabled');

            return;
        }

        $host = config('services.inbound_mail.imap_host');
        $port = config('services.inbound_mail.imap_port', 993);
        $user = config('services.inbound_mail.imap_user');
        $pass = config('services.inbound_mail.imap_password');
        $encryption = config('services.inbound_mail.imap_encryption', 'ssl');

        $mailbox = "{{$host}:{$port}/imap/{$encryption}}INBOX";

        $imap = @imap_open($mailbox, $user, $pass);
        if (! $imap) {
            Log::error('Inbound mail: IMAP connection failed — '.imap_last_error());
            ScheduleHeartbeat::fail('inbound-mail', 'IMAP connection failed');

            return;
        }

        $emails = imap_search($imap, 'UNSEEN');
        if (! $emails) {
            imap_close($imap);
            ScheduleHeartbeat::beat('inbound-mail', '0 messages');

            return;
        }

        $processed = 0;

        foreach ($emails as $msgNum) {
            try {
                $header = imap_headerinfo($imap, $msgNum);
                $from = $header->from[0]->mailbox.'@'.$header->from[0]->host;
                $subject = $this->decodeSubject($header->subject ?? '');
                $toAddress = $header->to[0]->mailbox.'@'.$header->to[0]->host;
                $body = $this->getBody($imap, $msgNum);

                $this->processMessage($from, $toAddress, $subject, $body);
                $processed++;

                // Mark as read
                imap_setflag_full($imap, (string) $msgNum, '\\Seen');
            } catch (\Throwable $e) {
                Log::error("Inbound mail error on msg #{$msgNum}: {$e->getMessage()}");
                imap_setflag_full($imap, (string) $msgNum, '\\Seen');
            }
        }

        imap_close($imap);
        ScheduleHeartbeat::beat('inbound-mail', "{$processed} processed");
        Log::info("Inbound mail: processed {$processed} messages");
    }

    private function processMessage(string $from, string $to, string $subject, string $body): void
    {
        // Check for (recipients: ...) directive in subject
        $recipientDirective = null;
        $simulate = false;

        if (preg_match('/\(recipients?:\s*([^)]+)\)/i', $subject, $m)) {
            $recipientDirective = $m[1];
            $subject = trim(str_replace($m[0], '', $subject));

            if (str_contains(strtolower($recipientDirective), 'simulate')) {
                $simulate = true;
                $recipientDirective = str_ireplace('simulate', '', $recipientDirective);
            }

            $recipientDirective = str_ireplace(
                ['sortie=', 'moniteurs'],
                ['members.s', 'instructors'],
                $recipientDirective
            );
        }

        // Resolve
        $resolved = $recipientDirective
            ? MailAliasService::resolveMultiple($recipientDirective)
            : MailAliasService::resolve($to);

        if (! $resolved || empty($resolved['emails'])) {
            $resolved = MailAliasService::resolve('bureau');
            $subject = "[Unknown: {$to}] {$subject}";
        }

        // Auth check
        $sender = User::where('primary_email', $from)->first();
        if (! $sender || (! $sender->isBureau() && ! $sender->hasRole('instructor'))) {
            EmailLog::create([
                'to_email' => $to, 'from_email' => $from, 'subject' => $subject,
                'body' => $body, 'status' => 'rejected', 'direction' => 'inbound',
                'error' => 'Unauthorized sender',
            ]);

            return;
        }

        if ($simulate) {
            Mail::raw(
                "Simulation for: {$resolved['label']}\n\n".count($resolved['emails'])." recipients:\n".implode("\n", $resolved['emails']),
                fn ($m) => $m->to($from)->subject("[SIMULATE] {$subject}")
            );

            return;
        }

        // Forward
        $sent = 0;
        foreach ($resolved['emails'] as $email) {
            try {
                Mail::html($body, fn ($m) => $m->to($email)->replyTo($from)->subject("[{$resolved['label']}] {$subject}"));
                $sent++;
            } catch (\Throwable $e) {
                Log::warning("Inbound forward failed to {$email}: {$e->getMessage()}");
            }
        }

        EmailLog::create([
            'to_email' => $to, 'from_email' => $from, 'subject' => $subject,
            'body' => $body, 'status' => 'forwarded', 'direction' => 'inbound',
            'error' => "Sent to {$sent}/".count($resolved['emails']),
        ]);

        // Confirmation to sender
        Mail::raw(
            "Your message '{$subject}' was sent to {$sent} recipients ({$resolved['label']}).",
            fn ($m) => $m->to($from)->subject("[Sent] {$subject} → {$resolved['label']}")
        );
    }

    private function decodeSubject(string $subject): string
    {
        $parts = imap_mime_header_decode($subject);
        $decoded = '';
        foreach ($parts as $part) {
            $decoded .= $part->text;
        }

        return $decoded ?: $subject;
    }

    private function getBody($imap, int $msgNum): string
    {
        $structure = imap_fetchstructure($imap, $msgNum);

        // Simple message
        if (! isset($structure->parts)) {
            $body = imap_body($imap, $msgNum);

            return $this->decodeBody($body, $structure->encoding ?? 0);
        }

        // Multipart — find HTML or plain text
        $html = '';
        $plain = '';

        foreach ($structure->parts as $i => $part) {
            $partBody = imap_fetchbody($imap, $msgNum, (string) ($i + 1));
            $decoded = $this->decodeBody($partBody, $part->encoding ?? 0);

            if ($part->subtype === 'HTML') {
                $html = $decoded;
            } elseif ($part->subtype === 'PLAIN') {
                $plain = $decoded;
            }
        }

        return $html ?: nl2br(e($plain));
    }

    private function decodeBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            3 => base64_decode($body),
            4 => quoted_printable_decode($body),
            default => $body,
        };
    }
}
