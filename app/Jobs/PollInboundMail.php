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

        if (empty($messages)) {
            ScheduleHeartbeat::beat('inbound-mail', '0 messages');

            return;
        }

        $processed = 0;
        foreach ($messages as $msg) {
            try {
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

            $headers = $this->parseRawHeaders($raw);
            $messages[] = [
                'from' => $this->extractEmail($headers['from'] ?? ''),
                'to' => $this->extractEmail($headers['to'] ?? ''),
                'subject' => $this->decodeMimeHeader($headers['subject'] ?? '(no subject)'),
                'body' => $this->parseRawBody($raw),
            ];

            @rename($file, $curDir.'/'.basename($file).':2,S');
        }

        return $messages;
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

    private function getImapBody($imap, int $num): string
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
        if (! $sender || (! $sender->isBureau() && ! $sender->hasRole('instructor'))) {
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

        // Extract event ID from alias for logging
        $eventId = null;
        if (preg_match('/^event-(\d+)@|^members\.s(\d+)@/', $to, $em)) {
            $eventId = (int) ($em[1] ?: $em[2]);
        }

        EmailLog::create([
            'event_id' => $eventId,
            'to_email' => $to, 'from_email' => $from, 'subject' => $subject,
            'body' => substr($body, 0, 5000), 'status' => 'forwarded',
            'direction' => 'inbound', 'error' => "Sent to {$sent}/".count($resolved['emails']),
        ]);

        Mail::raw(
            "Your message '{$subject}' was sent to {$sent} recipients ({$resolved['label']}).",
            fn ($m) => $m->to($from)->subject("[Sent] {$subject} → {$resolved['label']}")
        );
    }

    // ─── Raw email parsing (Maildir mode) ──────────────────

    private function parseRawHeaders(string $raw): array
    {
        $headerBlock = strstr($raw, "\r\n\r\n", true) ?: strstr($raw, "\n\n", true) ?: '';
        $headerBlock = preg_replace('/\r?\n\s+/', ' ', $headerBlock);

        $headers = [];
        foreach (explode("\n", $headerBlock) as $line) {
            if (preg_match('/^([A-Za-z-]+):\s*(.+)$/', trim($line), $m)) {
                $headers[strtolower($m[1])] = trim($m[2]);
            }
        }

        return $headers;
    }

    private function parseRawBody(string $raw): string
    {
        $parts = preg_split('/\r?\n\r?\n/', $raw, 2);
        $body = $parts[1] ?? '';

        if (preg_match('/boundary="?([^"\s;]+)"?/i', $parts[0] ?? '', $m)) {
            $boundary = $m[1];
            foreach (explode("--{$boundary}", $body) as $section) {
                if (stripos($section, 'text/html') !== false) {
                    $sp = preg_split('/\r?\n\r?\n/', $section, 2);

                    return $this->decodeRawPart($sp[1] ?? '', $section);
                }
            }
            foreach (explode("--{$boundary}", $body) as $section) {
                if (stripos($section, 'text/plain') !== false) {
                    $sp = preg_split('/\r?\n\r?\n/', $section, 2);

                    return '<pre>'.e($this->decodeRawPart($sp[1] ?? '', $section)).'</pre>';
                }
            }
        }

        return stripos($parts[0] ?? '', 'text/html') !== false
            ? $this->decodeRawPart($body, $parts[0] ?? '')
            : '<pre>'.e($this->decodeRawPart($body, $parts[0] ?? '')).'</pre>';
    }

    private function decodeRawPart(string $body, string $headers): string
    {
        if (stripos($headers, 'base64') !== false) {
            return base64_decode(trim($body));
        }
        if (stripos($headers, 'quoted-printable') !== false) {
            return quoted_printable_decode($body);
        }

        return $body;
    }

    private function extractEmail(string $header): string
    {
        return strtolower(preg_match('/<([^>]+)>/', $header, $m) ? $m[1] : trim($header));
    }

    private function decodeMimeHeader(string $header): string
    {
        if (preg_match_all('/=\?([^?]+)\?([BQ])\?([^?]+)\?=/i', $header, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $decoded = strtoupper($m[2]) === 'B' ? base64_decode($m[3]) : quoted_printable_decode(str_replace('_', ' ', $m[3]));
                $header = str_replace($m[0], $decoded, $header);
            }
        }

        return $header;
    }
}
