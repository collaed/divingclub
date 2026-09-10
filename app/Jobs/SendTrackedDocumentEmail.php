<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DocumentDispatchRecipient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendTrackedDocumentEmail implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $recipientId) {}

    public function handle(): void
    {
        $recipient = DocumentDispatchRecipient::with('dispatch.file')->find($this->recipientId);
        if (! $recipient || $recipient->sent_at !== null) {
            return;
        }

        try {
            $url = route('tracked-doc.open', $recipient->token);
            $html = view('emails.tracked-document', [
                'dispatch' => $recipient->dispatch,
                'url' => $url,
            ])->render();

            Mail::html($html, fn ($m) => $m->to($recipient->email)->subject($recipient->dispatch->subject));

            $recipient->update(['sent_at' => now(), 'send_error' => null]);
        } catch (Throwable $e) {
            $recipient->update(['send_error' => mb_substr($e->getMessage(), 0, 250)]);
            report($e);
        }
    }
}
