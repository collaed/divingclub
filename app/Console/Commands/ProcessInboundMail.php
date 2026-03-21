<?php

namespace App\Console\Commands;

use App\Models\EmailLog;
use App\Services\MailAliasService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Process inbound email piped from Postfix.
 *
 * Postfix config (in /etc/aliases or transport):
 *   clubcep: "| /usr/bin/php /opt/deploy/apps/divingclub/artisan mail:inbound"
 *
 * Or test manually:
 *   echo "Test body" | php artisan mail:inbound --to=bureau@clubcep.eu --from=eddy@test.com --subject="Test"
 */
class ProcessInboundMail extends Command
{
    protected $signature = 'mail:inbound
        {--to= : Recipient alias (e.g. bureau@clubcep.eu)}
        {--from= : Sender email}
        {--subject= : Email subject}';

    protected $description = 'Process inbound email and forward to alias recipients';

    public function handle(): int
    {
        $to = $this->option('to') ?? '';
        $from = $this->option('from') ?? 'unknown@unknown';
        $subject = $this->option('subject') ?? '(no subject)';
        $body = stream_get_contents(STDIN) ?: '';

        $resolved = MailAliasService::resolve($to);

        if (! $resolved || empty($resolved['emails'])) {
            $this->error("Unknown alias or no recipients: {$to}");

            return self::FAILURE;
        }

        $count = count($resolved['emails']);
        $this->info("Alias '{$to}' → {$resolved['label']} ({$count} recipients)");

        foreach ($resolved['emails'] as $email) {
            Mail::raw($body, function ($message) use ($email, $from, $subject, $resolved) {
                $message->to($email)
                    ->replyTo($from)
                    ->subject("[{$resolved['label']}] {$subject}");
            });

            $this->line("  → {$email}");
        }

        EmailLog::create([
            'event_id' => $this->extractEventId($to),
            'to_email' => $to,
            'from_email' => $from,
            'subject' => $subject,
            'body' => $body,
            'status' => 'forwarded',
        ]);

        $this->info("Forwarded to {$count} recipients.");

        return self::SUCCESS;
    }

    private function extractEventId(string $address): ?int
    {
        $local = strtolower(explode('@', $address)[0]);

        return preg_match('/^event-(\d+)$/', $local, $m) ? (int) $m[1] : null;
    }
}
