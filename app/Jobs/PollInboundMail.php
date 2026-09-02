<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EmailLog;
use App\Models\MailConversation;
use App\Models\User;
use App\Services\ConversationService;
use App\Services\InboundMailDeduplicator;
use App\Services\InboundMailFilter;
use App\Services\MailAliasService;
use App\Services\MailBalancer;
use App\Services\ScheduleHeartbeat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use IMAP\Connection;
use ZBateson\MailMimeParser\Header\AddressHeader;
use ZBateson\MailMimeParser\IMessage;
use ZBateson\MailMimeParser\MailMimeParser;

/**
 * Poll for inbound alias emails — two modes:
 *
 * Maildir: INBOUND_MAIL_MODE=maildir  INBOUND_MAILDIR=/home/inbound/Maildir
 * IMAP:    INBOUND_MAIL_MODE=imap     INBOUND_IMAP_HOST/PORT/USER/PASSWORD/ENCRYPTION
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

        $mode = config('services.inbound_mail.mode', 'maildir');
        $messages = $mode === 'imap' ? $this->fetchImap() : $this->fetchMaildir();

        if ($messages === []) {
            ScheduleHeartbeat::beat('inbound-mail', '0 messages');

            return;
        }

        $processed = 0;
        foreach ($messages as $msg) {
            try {
                if (! InboundMailDeduplicator::markProcessed($msg['message_id'] ?? null)) {
                    continue;
                }
                $this->processMessage($msg['from'], $msg['to'], $msg['subject'], $msg['body']);
                $processed++;
            } catch (\Throwable $e) {
                Log::error("Inbound mail error: {$e->getMessage()}");
            }
        }

        ScheduleHeartbeat::beat('inbound-mail', "{$processed} processed");
    }

    // ─── Maildir mode ──────────────────────────────────────

    private function fetchMaildir(): array
    {
        $maildir = config('services.inbound_mail.maildir', '/home/inbound/Maildir');
        $newDir = rtrim($maildir, '/').'/new';
        $curDir = rtrim($maildir, '/').'/cur';

        if (! is_dir($newDir)) {
            return [];
        }

        $messages = [];
        foreach (glob("{$newDir}/*") as $file) {
            $raw = @file_get_contents($file);
            if (! $raw) {
                continue;
            }

            $messages[] = $this->parseRaw($raw);

            @rename($file, $curDir.'/'.basename($file).':2,S');
        }

        return $messages;
    }

    /**
     * Parse a raw RFC822 message into the normalized fields we forward.
     *
     * Uses zbateson/mail-mime-parser for robust MIME handling (multipart,
     * nested parts, base64/quoted-printable, and encoded headers), replacing
     * the previous hand-rolled boundary/encoding logic.
     *
     * @return array{from: string, to: string, subject: string, body: string, message_id: ?string}
     */
    private function parseRaw(string $raw): array
    {
        $message = (new MailMimeParser)->parse($raw, false);

        $html = $message->getHtmlContent();
        $text = $message->getTextContent();
        $body = $html !== null && $html !== ''
            ? $html
            : '<pre>'.e($text ?? '').'</pre>';

        return [
            'from' => $this->addressFromHeader($message, 'From'),
            'to' => $this->addressFromHeader($message, 'To'),
            'subject' => (string) ($message->getHeaderValue('Subject') ?: '(no subject)'),
            'body' => $body,
            'message_id' => $message->getHeaderValue('Message-ID') ?: null,
        ];
    }

    /** Extract the first bare email address from an address header. */
    private function addressFromHeader(IMessage $message, string $name): string
    {
        $header = $message->getHeader($name);
        if ($header instanceof AddressHeader) {
            $first = $header->getAddresses()[0] ?? null;
            if ($first !== null) {
                return strtolower(trim($first->getEmail()));
            }
        }

        return strtolower(trim((string) $message->getHeaderValue($name)));
    }

    // ─── IMAP mode ─────────────────────────────────────────

    private function fetchImap(): array
    {
        $host = config('services.inbound_mail.imap_host');
        $port = config('services.inbound_mail.imap_port', 993);
        $user = config('services.inbound_mail.imap_user');
        $pass = config('services.inbound_mail.imap_password');
        $enc = config('services.inbound_mail.imap_encryption', 'ssl');

        $flags = match ($enc) {
            'ssl' => '/imap/ssl',
            'notls' => '/imap/notls/novalidate-cert',
            default => '/imap/ssl',
        };

        $imap = @imap_open("{{$host}:{$port}{$flags}}INBOX", $user, $pass);
        if (! $imap) {
            Log::error('Inbound IMAP failed: '.imap_last_error());

            return [];
        }

        $messages = [];
        foreach (imap_search($imap, 'UNSEEN') ?: [] as $num) {
            $hdr = imap_headerinfo($imap, $num);
            $messages[] = [
                'from' => strtolower($hdr->from[0]->mailbox.'@'.$hdr->from[0]->host),
                'to' => strtolower($hdr->to[0]->mailbox.'@'.$hdr->to[0]->host),
                'subject' => $this->decodeImapSubject($hdr->subject ?? ''),
                'body' => $this->getImapBody($imap, $num),
                'message_id' => $hdr->message_id ?? null,
            ];
            imap_setflag_full($imap, (string) $num, '\\Seen');
        }

        imap_close($imap);

        return $messages;
    }

    private function decodeImapSubject(string $subject): string
    {
        $parts = imap_mime_header_decode($subject);
        $decoded = '';
        foreach ($parts as $part) {
            $decoded .= $part->text;
        }

        return $decoded ?: $subject;
    }

    private function getImapBody(Connection $imap, int $num): string
    {
        $struct = imap_fetchstructure($imap, $num);

        if (! isset($struct->parts)) {
            return $this->decodeImapPart(imap_body($imap, $num), $struct->encoding ?? 0);
        }

        foreach ($struct->parts as $i => $part) {
            if ($part->subtype === 'HTML') {
                return $this->decodeImapPart(imap_fetchbody($imap, $num, (string) ($i + 1)), $part->encoding ?? 0);
            }
        }
        foreach ($struct->parts as $i => $part) {
            if ($part->subtype === 'PLAIN') {
                return '<pre>'.e($this->decodeImapPart(imap_fetchbody($imap, $num, (string) ($i + 1)), $part->encoding ?? 0)).'</pre>';
            }
        }

        return imap_body($imap, $num);
    }

    private function decodeImapPart(string $body, int $encoding): string
    {
        return match ($encoding) {
            3 => base64_decode($body),
            4 => quoted_printable_decode($body),
            default => $body,
        };
    }

    // ─── Message processing ────────────────────────────────

    private function processMessage(string $from, string $to, string $subject, string $body): void
    {
        // Conversation reply channel: sas+conv.{token}@ replies thread back to
        // the initiator, keeping the member's real address private.
        $conversation = ConversationService::matchToken($to);
        if ($conversation !== null) {
            $this->forwardConversationReply($conversation, $from, $subject, $body);

            return;
        }

        $recipientDirective = null;
        $simulate = false;

        if (preg_match('/\(recipients?:\s*([^)]+)\)/i', $subject, $m)) {
            $recipientDirective = $m[1];
            $subject = trim(str_replace($m[0], '', $subject));

            if (str_contains(strtolower($recipientDirective), 'simulate')) {
                $simulate = true;
                $recipientDirective = str_ireplace('simulate', '', $recipientDirective);
            }

            $recipientDirective = str_ireplace(['sortie=', 'moniteurs'], ['members.s', 'instructors'], $recipientDirective);
        }

        $resolved = $recipientDirective
            ? MailAliasService::resolveMultiple($recipientDirective)
            : MailAliasService::resolve($to);

        if (! $resolved || empty($resolved['emails'])) {
            $resolved = MailAliasService::resolve('bureau');
            $subject = "[Unknown: {$to}] {$subject}";
        }

        $sender = User::where('primary_email', $from)->first();
        $isOpenForward = ($resolved['auth_level'] ?? null) === 'open';
        if (! $isOpenForward && (! $sender || (! $sender->isBureau() && ! $sender->hasRole('instructor')))) {
            EmailLog::create([
                'to_email' => $to, 'from_email' => $from, 'subject' => $subject,
                'body' => substr($body, 0, 5000), 'status' => 'rejected',
                'direction' => 'inbound', 'error' => 'Unauthorized sender',
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

        $sent = 0;
        foreach ($resolved['emails'] as $email) {
            try {
                Mail::html($body, fn ($m) => $m->to($email)->replyTo($from)->subject("[{$resolved['label']}] {$subject}"));
                $sent++;
            } catch (\Throwable $e) {
                Log::warning("Inbound forward failed to {$email}: {$e->getMessage()}");
            }
        }

        // Extract event ID from alias for logging (event-{id} or members.s{id}).
        $eventId = null;
        if (preg_match('/event[.\-](\d+)/', $to, $em) || preg_match('/members\.s(\d+)/', $to, $em)) {
            $eventId = (int) $em[1];
        }

        // Filter inbound content before logging
        $filtered = InboundMailFilter::filter($body, $eventId, $from);

        EmailLog::create([
            'event_id' => $eventId,
            'to_email' => $to, 'from_email' => $from, 'subject' => $subject,
            'body' => substr($filtered['body'], 0, 5000),
            'status' => $filtered['needs_review'] ? 'pending_review' : 'forwarded',
            'direction' => 'inbound',
            'authorized' => ! $filtered['needs_review'],
            'error' => $filtered['needs_review']
                ? "Needs review: {$filtered['review_reason']}"
                : "Sent to {$sent}/".count($resolved['emails']),
        ]);

        // Passthrough forwards (e.g. sas.eddy → club Gmail) must not send an
        // auto-confirmation back to the (possibly external) original sender.
        if (($resolved['auth_level'] ?? null) !== 'open') {
            Mail::raw(
                "Your message '{$subject}' was sent to {$sent} recipients ({$resolved['label']}).",
                fn ($m) => $m->to($from)->subject("[Sent] {$subject} → {$resolved['label']}")
            );
        }
    }

    /**
     * Forward an external party's reply on a proxied conversation back to the
     * initiator, keeping the member's real address private. Event-linked
     * conversations append the reply to the event page via email_log.event_id.
     */
    private function forwardConversationReply(MailConversation $conversation, string $from, string $subject, string $body): void
    {
        $filtered = InboundMailFilter::filter($body, $conversation->event_id, $from);
        $initiator = $conversation->initiator;

        if ($initiator?->primary_email) {
            MailBalancer::configureForNext();
            Mail::html($filtered['body'], fn ($m) => $m->to($initiator->primary_email)
                ->replyTo($conversation->sas_alias)
                ->subject("[CEP] {$subject}"));
        }

        ConversationService::recordActivity($conversation);

        EmailLog::create([
            'event_id' => $conversation->event_id,
            'user_id' => $conversation->initiator_user_id,
            'to_email' => $conversation->sas_alias,
            'alias' => $conversation->sas_alias,
            'from_email' => $from,
            'subject' => $subject,
            'body' => substr($filtered['body'], 0, 5000),
            'status' => $filtered['needs_review'] ? 'pending_review' : 'forwarded',
            'direction' => 'inbound',
            'authorized' => ! $filtered['needs_review'],
            'error' => $filtered['needs_review'] ? "Needs review: {$filtered['review_reason']}" : null,
        ]);
    }
}
