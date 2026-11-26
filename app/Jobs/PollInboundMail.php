<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EmailLog;
use App\Models\MailForwardingRule;
use App\Services\InboundMailFilter;
use App\Services\MailAliasService;
use App\Services\MailBalancer;
use App\Services\ScheduleHeartbeat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use IMAP\Connection;

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
                // Postfix local delivery writes one maildir file per envelope
                // recipient, each stamped with its own X-Original-To — the
                // "To:" header is just whatever the sender typed and is
                // identical across copies, so a message sent to two local
                // aliases at once (e.g. one To, one Bcc) would otherwise
                // resolve every copy to the same (visible) alias instead of
                // each copy's actual recipient.
                'to' => $this->extractEmail($headers['x-original-to'] ?? $headers['to'] ?? ''),
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
        $rule = MailForwardingRule::where('enabled', true)
            ->whereRaw('LOWER(from_address) = ?', [strtolower($to)])
            ->first();
        if ($rule) {
            $this->forwardViaRule($from, $to, $subject, $body, $rule);

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

        if (! MailAliasService::authorizedFor($resolved, $from, $to)) {
            EmailLog::create([
                'to_email' => $to, 'from_email' => $from, 'subject' => $subject,
                'body' => substr($this->toDisplayText($body), 0, 5000), 'status' => 'rejected',
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

        // Extract event ID from alias for logging
        $eventId = null;
        if (preg_match('/event[.\-](\d+)/', $to, $em)) {
            $eventId = (int) $em[1];
        }

        // Filter inbound content before logging — message-level, so computed
        // once and applied to every recipient's own log row below.
        $filtered = InboundMailFilter::filter($body, $eventId, $from);
        $finalSubject = "[{$resolved['label']}] {$subject}";
        $contactTokens = $resolved['contact_unsubscribe_tokens'] ?? [];

        // One EmailLog row per recipient (not one for the whole batch) so a
        // failure on one address doesn't obscure whether the others went
        // through, and so which relay handled each send is traceable. They
        // all share one batch_id so the Communications panel (and the bulk
        // edit in EmailController::updateLog()) still treats them as a
        // single thread regardless of which wall-clock second each row's
        // own per-recipient send/insert lands in.
        $sent = 0;
        $batchId = (string) Str::uuid();
        $displayBody = substr($this->toDisplayText($filtered['body']), 0, 5000);
        foreach ($resolved['emails'] as $email) {
            $status = 'forwarded';
            $error = null;
            $recipientBody = $body.MailAliasService::footerHtml($contactTokens[$email] ?? null);

            try {
                Mail::html($recipientBody, fn ($m) => $m->to($email)->replyTo($from)->subject($finalSubject));
                $sent++;
            } catch (\Throwable $e) {
                $status = 'failed';
                $error = $e->getMessage();
                Log::warning("Inbound forward failed to {$email}: {$e->getMessage()}");
            }

            if ($filtered['needs_review'] && $status !== 'failed') {
                $status = 'pending_review';
                $error = "Needs review: {$filtered['review_reason']}";
            }

            EmailLog::create([
                'event_id' => $eventId,
                'to_email' => $email, 'from_email' => $from, 'subject' => $finalSubject,
                'body' => $displayBody,
                'status' => $status,
                'direction' => 'inbound',
                'authorized' => ! $filtered['needs_review'],
                'error' => $error,
                'provider' => MailBalancer::lastProvider(),
                'batch_id' => $batchId,
            ]);
        }

        Mail::raw(
            "Your message '{$subject}' was sent to {$sent} recipients ({$resolved['label']}).",
            fn ($m) => $m->to($from)->subject("[Sent] {$subject} → {$resolved['label']}")
        );
    }

    /**
     * An admin-configured forwarding rule matched this recipient — it isn't
     * a club-internal alias, so it bypasses alias resolution and the
     * bureau/instructor sender check entirely — just forward it,
     * unconditionally, to every target the rule lists.
     */
    private function forwardViaRule(string $from, string $to, string $subject, string $body, MailForwardingRule $rule): void
    {
        // One EmailLog row per target (not one for the whole rule) — same
        // reasoning as processMessage()'s forwarding loop: a failure on one
        // address must not hide whether the others went through, or which
        // relay each one used. They share one batch_id for the same reason
        // the forwarding loop below does — see the comment there.
        $batchId = (string) Str::uuid();
        $displayBody = substr($this->toDisplayText($body), 0, 5000);
        foreach ($rule->targets() as $target) {
            $status = 'forwarded';
            $error = "Redirected to {$target}";

            try {
                Mail::html($body, fn ($m) => $m->to($target)->replyTo($from)->subject($subject));
            } catch (\Throwable $e) {
                $status = 'failed';
                $error = "Redirect to {$target} failed: {$e->getMessage()}";
                Log::warning("Inbound forwarding-rule redirect failed: {$e->getMessage()}");
            }

            EmailLog::create([
                'to_email' => $target, 'from_email' => $from, 'subject' => $subject,
                'alias' => $to, 'body' => $displayBody, 'status' => $status,
                'direction' => 'inbound', 'authorized' => true, 'error' => $error,
                'provider' => MailBalancer::lastProvider(),
                'batch_id' => $batchId,
            ]);
        }
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

    /**
     * The body stored for display (Communications panel, EmailLog) must be
     * plain, human-readable text — but parseRawBody()/getImapBody() hand
     * back the chosen MIME part's *source* (HTML markup when an HTML part
     * exists, otherwise a `<pre>`-wrapped, already-entity-escaped plain-text
     * part), because that's what Mail::html() needs to forward it looking
     * right in the recipient's own mail client. Blade's `{{ }}` then
     * escapes that source a second time, so an HTML part shows as literal
     * tag soup and a plain-text part shows its entities twice-escaped.
     * Converting to text here (not in the extraction methods themselves)
     * keeps the forwarded email untouched while fixing what gets logged.
     */
    private function toDisplayText(string $html): string
    {
        $text = preg_replace('/<(br|\/p|\/div|\/li|\/tr)\s*\/?>/i', "\n", $html);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
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
