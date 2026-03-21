<?php

namespace App\Providers;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class StagingMailServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! config('app.staging_mode')) {
            return;
        }

        // Force mail to log driver so nothing actually sends
        config(['mail.default' => 'log']);

        // Capture every outgoing email into email_log
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $message = $event->message;
            $to = collect($message->getTo())->map(fn ($a) => $a->getAddress())->implode(', ');
            $eventId = null;
            foreach ($message->getTo() as $addr) {
                if (preg_match('/^event-(\d+)@/i', $addr->getAddress(), $m)) {
                    $eventId = (int) $m[1];
                    break;
                }
            }

            EmailLog::create([
                'event_id' => $eventId,
                'to_email' => $to,
                'subject' => $message->getSubject() ?? '(no subject)',
                'body' => $message->getHtmlBody() ?? $message->getTextBody() ?? '',
                'from_email' => collect($message->getFrom())->map(fn ($a) => $a->getAddress())->first(),
                'from_name' => collect($message->getFrom())->map(fn ($a) => $a->getName())->first(),
                'status' => 'staging_captured',
            ]);
        });
    }
}
