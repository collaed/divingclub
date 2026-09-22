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
        $recipient = DocumentDispatchRecipient::with('dispatch.file', 'user.emails')->find($this->recipientId);
        if (! $recipient || $recipient->sent_at !== null) {
            return;
        }

        try {
            $url = route('tracked-doc.open', $recipient->token);
            $html = view('emails.tracked-document', [
                'dispatch' => $recipient->dispatch,
                'url' => $url,
            ])->render();

            Mail::html($html, fn ($m) => $m->from(...$this->fromAddress())->to($this->addressesFor($recipient))->subject($recipient->dispatch->subject));

            $recipient->update(['sent_at' => now(), 'send_error' => null]);
        } catch (Throwable $e) {
            $recipient->update(['send_error' => mb_substr($e->getMessage(), 0, 250)]);
            report($e);
        }
    }

    /**
     * A real, monitored inbox rather than the app-wide default (an alias
     * mailbox meant for inbound routing, not for a recipient to reply to) —
     * a tracked document is exactly the kind of send someone might reply to.
     *
     * @return array{0: string, 1: string|null}
     */
    private function fromAddress(): array
    {
        return [config('club.contact_email'), config('mail.from.name')];
    }

    /**
     * Every address this member wants club communication at — the stored
     * primary plus any verified secondary marked "communicate here as well"
     * (UserEmail::receive_mail) — sent as one message with one tracking
     * token, so it counts as a single send and an open registers against the
     * member regardless of which mailbox they opened it from.
     *
     * @return list<string>
     */
    private function addressesFor(DocumentDispatchRecipient $recipient): array
    {
        $extra = $recipient->user?->emails
            ->where('is_verified', true)
            ->where('receive_mail', true)
            ->pluck('email') ?? collect();

        return $extra->push($recipient->email)
            ->map(fn ($email): string => mb_strtolower($email))
            ->unique()
            ->values()
            ->all();
    }
}
