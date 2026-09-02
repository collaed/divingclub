<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EmailLog;
use App\Models\MailConversation;
use App\Models\User;
use App\Services\ConversationService;
use App\Services\InboundMailDeduplicator;
use App\Services\InboundMailFilter;
use App\Services\MailAliasService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Process inbound email piped from Postfix.
 *
 * Supports:
 *   - Direct alias: mail to bureau@clubcep.eu, event-42@clubcep.eu, members.s1862@clubcep.eu
 *   - Subject directive: (recipients: bureau, moniteurs, sortie=25, Michel B)
 *   - Legacy SAS block in body (To:/Cc: lines)
 *
 * Postfix pipe:
 *   /usr/bin/php /path/to/artisan mail:inbound --to=${recipient} --from=${sender}
 *
 * Test:
 *   echo "Test body" | php artisan mail:inbound --to=bureau@clubcep.eu --from=eddy@test.com
 */
class ProcessInboundMail extends Command
{
    protected $signature = 'mail:inbound
        {--to= : Recipient alias}
        {--from= : Sender email}
        {--subject= : Email subject (optional, read from stdin if piped)}';

    protected $description = 'Process inbound email and forward to alias recipients';

    public function handle(): int
    {
        $to = $this->option('to') ?? '';
        $from = $this->option('from') ?? 'unknown@unknown';
        $subject = $this->option('subject') ?? '';
        $body = stream_get_contents(STDIN) ?: '';

        // Deduplicate by Message-ID (replicates the legacy mailIds/ folder).
        $messageId = InboundMailDeduplicator::extractMessageId($body);
        if (! InboundMailDeduplicator::markProcessed($messageId)) {
            $this->warn("Duplicate Message-ID {$messageId} — skipping.");

            return self::SUCCESS;
        }

        // Extract subject from raw email if not provided as option
        if (! $subject && preg_match('/^Subject:\s*(.+)$/mi', $body, $m)) {
            $subject = trim($m[1]);
        }
        if (! $subject) {
            $subject = '(no subject)';
        }

        // Conversation reply channel: sas+conv.{token}@ replies thread back to
        // the initiator, keeping the member's real address private.
        $conversation = ConversationService::matchToken($to);
        if ($conversation !== null) {
            return $this->forwardConversationReply($conversation, $from, $subject, $body);
        }

        // Check for (recipients: ...) directive in subject
        $recipientDirective = null;
        $simulate = false;
        if (preg_match('/\(recipients?:\s*([^)]+)\)/i', $subject, $m)) {
            $recipientDirective = $m[1];
            $subject = trim(str_replace($m[0], '', $subject));

            // Check for simulate flag
            if (str_contains(strtolower($recipientDirective), 'simulate')) {
                $simulate = true;
                $recipientDirective = str_ireplace('simulate', '', $recipientDirective);
            }

            // Map legacy directive names
            $recipientDirective = str_ireplace(
                ['sortie=', 'moniteurs', 'bureau'],
                ['members.s', 'instructors', 'bureau'],
                $recipientDirective
            );
        }

        // Resolve recipients
        if ($recipientDirective) {
            $resolved = MailAliasService::resolveMultiple($recipientDirective);
        } else {
            $resolved = MailAliasService::resolve($to);
        }

        if (! $resolved || empty($resolved['emails'])) {
            // Unknown alias — forward to bureau
            $sender = User::where('primary_email', $from)->first();
            if (! $sender) {
                $this->error("Unknown alias {$to} from unknown sender {$from}");
                $this->logMail($to, $from, $subject, $body, 'rejected', 'Unknown alias + unknown sender');

                return self::FAILURE;
            }

            $resolved = MailAliasService::resolve('bureau');
            $subject = "[Unknown: {$to}] {$subject}";
        }

        // Authorization check
        if (! MailAliasService::isAuthorized($from, $to) && ! $recipientDirective) {
            // If using directive, check if sender is bureau/instructor
            $sender = User::where('primary_email', $from)->first();
            if (! $sender || (! $sender->isBureau() && ! $sender->hasRole('instructor'))) {
                $this->error("Unauthorized sender {$from}");
                $this->logMail($to, $from, $subject, $body, 'rejected', 'Unauthorized sender');

                return self::FAILURE;
            }
        }

        $count = count($resolved['emails']);
        $this->info("Alias '{$to}' → {$resolved['label']} ({$count} recipients)");

        if ($simulate) {
            $this->warn('SIMULATE MODE — not sending');
            foreach ($resolved['emails'] as $email) {
                $this->line("  [sim] {$email}");
            }

            // Send simulation report back to sender
            Mail::raw(
                "Simulation for: {$resolved['label']}\n\n{$count} recipients:\n".implode("\n", $resolved['emails']),
                fn ($m) => $m->to($from)->subject("[SIMULATE] {$subject}")
            );

            $this->logMail($to, $from, $subject, $body, 'simulated', "{$count} recipients");

            return self::SUCCESS;
        }

        // Send to each recipient
        $sent = 0;
        foreach ($resolved['emails'] as $email) {
            try {
                Mail::html($body, function ($message) use ($email, $from, $subject, $resolved): void {
                    $message->to($email)
                        ->replyTo($from)
                        ->subject("[{$resolved['label']}] {$subject}");
                });
                $this->line("  → {$email}");
                $sent++;
            } catch (\Throwable $e) {
                $this->error("  ✗ {$email}: {$e->getMessage()}");
            }
        }

        $this->logMail($to, $from, $subject, $body, 'forwarded', "Sent to {$sent}/{$count}");

        // Passthrough forwards (e.g. sas.eddy → club Gmail) must not send an
        // auto-confirmation back to the (possibly external) original sender.
        if (($resolved['auth_level'] ?? null) !== 'open') {
            // Send confirmation to sender
            Mail::raw(
                "Your message '{$subject}' was sent to {$sent} recipients ({$resolved['label']}).",
                fn ($m) => $m->to($from)->subject("[Sent] {$subject} → {$resolved['label']}")
            );
        }

        $this->info("Forwarded to {$sent}/{$count} recipients.");

        return self::SUCCESS;
    }

    private function logMail(string $to, string $from, string $subject, string $body, string $status, ?string $error = null): void
    {
        EmailLog::create([
            'event_id' => $this->eventIdFromAlias($to),
            'to_email' => $to,
            'alias' => $to,
            'from_email' => $from,
            'subject' => $subject,
            'body' => $body,
            'status' => $status,
            'direction' => 'inbound',
            'authorized' => $status !== 'rejected',
            'error' => $error,
        ]);
    }

    /** Extract an event ID from an alias (event-{id} or members.s{id}). */
    private function eventIdFromAlias(string $alias): ?int
    {
        if (preg_match('/event[.\-](\d+)/', $alias, $m) || preg_match('/members\.s(\d+)/', $alias, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Forward an external party's reply on a proxied conversation back to the
     * initiator. Event-linked conversations append to the event page via
     * email_log.event_id.
     */
    private function forwardConversationReply(MailConversation $conversation, string $from, string $subject, string $body): int
    {
        $filtered = InboundMailFilter::filter($body, $conversation->event_id, $from);
        $initiator = $conversation->initiator;

        if ($initiator?->primary_email) {
            Mail::html($filtered['body'], fn ($m) => $m->to($initiator->primary_email)
                ->replyTo($conversation->sas_alias)
                ->subject("[CEP] {$subject}"));
            $this->line("  → {$initiator->primary_email} (conversation reply)");
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

        return self::SUCCESS;
    }
}
