<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Event;
use App\Models\EventAutomationRule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendEventAutomationEmail implements ShouldQueue
{
    use Dispatchable, Queueable;

    /** @param  array<int, string>  $recipients */
    public function __construct(public int $ruleId, public int $eventId, public array $recipients) {}

    public function handle(): void
    {
        $rule = EventAutomationRule::find($this->ruleId);
        $event = Event::find($this->eventId);
        if (! $rule || ! $event || $this->recipients === []) {
            return;
        }

        try {
            $html = view('emails.event-automation', ['event' => $event, 'body' => $rule->email_body])->render();
            $subject = $rule->email_subject ?: __(':event — automated notice', ['event' => $event->title]);

            Mail::html($html, fn ($m) => $m->to($this->recipients)->subject($subject));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
