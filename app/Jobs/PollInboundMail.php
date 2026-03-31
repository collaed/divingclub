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
 * Option A (simplified): Watch a Maildir for new messages.
 *
 * Reads raw .eml files from Maildir/new/, processes them, moves to Maildir/cur/.
 * No IMAP/Dovecot needed — just Postfix delivering to a local user.
 *
 * Configure in .env:
 *   INBOUND_MAIL_ENABLED=true
 *   INBOUND_MAILDIR=/home/inbound/Maildir
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

        $maildir = config('services.inbound_mail.maildir', '/home/inbound/Maildir');
        $newDir = rtrim($maildir, '/').'/new';
        $curDir = rtrim($maildir, '/').'/cur';

        if (! is_dir($newDir)) {
            ScheduleHeartbeat::beat('inbound-mail', 'Maildir not found');

            return;
        }

        $files = glob("{$newDir}/*");
        if (empty($files)) {
            ScheduleHeartbeat::beat('inbound-mail', '0 messages');

            return;
        }

        $processed = 0;

        foreach ($files as $file) {
            try {
                $raw = file_get_contents($file);
                if (! $raw) {
                    continue;
                }

                $headers = $this->parseHeaders($raw);
                $body = $this->parseBody($raw);

                $from = $this->extractEmail($headers['from'] ?? '');
                $to = $this->extractEmail($headers['to'] ?? '');
                $subject = $this->decodeHeader($headers['subject'] ?? '(no subject)');

                $this->processMessage($from, $to, $subject, $body);
                $processed++;

                // Move to cur/ (processed)
                rename($file, $curDir.'/'.basename($file).':2,S');
            } catch (\Throwable $e) {
                Log::error('Inbound mail error on '.basename($file).": {$e->getMessage()}");
                // Move to cur/ anyway to avoid reprocessing
                @rename($file, $curDir.'/'.basename($file).':2,S');
            }
        }

        ScheduleHeartbeat::beat('inbound-mail', "{$processed} processed");
        if ($processed) {
            Log::info("Inbound mail: processed {$processed} messages");
        }
    }

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

            $recipientDirective = str_ireplace(
                ['sortie=', 'moniteurs'],
                ['members.s', 'instructors'],
                $recipientDirective
            );
        }

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

        EmailLog::create([
            'to_email' => $to, 'from_email' => $from, 'subject' => $subject,
            'body' => substr($body, 0, 5000), 'status' => 'forwarded',
            'direction' => 'inbound', 'error' => "Sent to {$sent}/".count($resolved['emails']),
        ]);

        Mail::raw(
            "Your message '{$subject}' was sent to {$sent} recipients ({$resolved['label']}).",
            fn ($m) => $m->to($from)->subject("[Sent] {$subject} → {$resolved['label']}")
        );
    }

    /** Parse email headers from raw message. */
    private function parseHeaders(string $raw): array
    {
        $headerBlock = strstr($raw, "\r\n\r\n", true) ?: strstr($raw, "\n\n", true) ?: '';
        $headerBlock = preg_replace('/\r?\n\s+/', ' ', $headerBlock); // unfold

        $headers = [];
        foreach (explode("\n", $headerBlock) as $line) {
            if (preg_match('/^([A-Za-z-]+):\s*(.+)$/', trim($line), $m)) {
                $headers[strtolower($m[1])] = trim($m[2]);
            }
        }

        return $headers;
    }

    /** Extract body (prefer HTML, fall back to plain text). */
    private function parseBody(string $raw): string
    {
        $parts = preg_split('/\r?\n\r?\n/', $raw, 2);
        $body = $parts[1] ?? '';

        // If multipart, try to find HTML part
        if (preg_match('/boundary="?([^"\s;]+)"?/i', $parts[0] ?? '', $m)) {
            $boundary = $m[1];
            $sections = explode("--{$boundary}", $body);

            foreach ($sections as $section) {
                if (stripos($section, 'text/html') !== false) {
                    $sectionParts = preg_split('/\r?\n\r?\n/', $section, 2);

                    return $this->decodeBodyPart($sectionParts[1] ?? '', $section);
                }
            }
            // Fall back to first text/plain
            foreach ($sections as $section) {
                if (stripos($section, 'text/plain') !== false) {
                    $sectionParts = preg_split('/\r?\n\r?\n/', $section, 2);

                    return '<pre>'.e($this->decodeBodyPart($sectionParts[1] ?? '', $section)).'</pre>';
                }
            }
        }

        // Simple message (not multipart)
        if (stripos($parts[0] ?? '', 'text/html') !== false) {
            return $this->decodeBodyPart($body, $parts[0] ?? '');
        }

        return '<pre>'.e($this->decodeBodyPart($body, $parts[0] ?? '')).'</pre>';
    }

    private function decodeBodyPart(string $body, string $headers): string
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
        if (preg_match('/<([^>]+)>/', $header, $m)) {
            return strtolower($m[1]);
        }

        return strtolower(trim($header));
    }

    private function decodeHeader(string $header): string
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
