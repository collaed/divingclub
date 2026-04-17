<?php

namespace App\Jobs;

use App\Models\EquipmentLoan;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendLoanRecapEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $userId) {}

    public function handle(): void
    {
        $loans = EquipmentLoan::with('equipment', 'event')
            ->where('user_id', $this->userId)
            ->whereNull('returned_at')
            ->whereNull('loan_email_sent_at')
            ->get();

        if ($loans->isEmpty()) {
            return;
        }

        $user = User::with('detail')->find($this->userId);
        if (! $user?->primary_email) {
            return;
        }

        $lines = $loans->map(fn ($l) => sprintf(
            '%s #%s (%s)%s',
            ucfirst($l->equipment->type),
            $l->equipment->short_number ?? $l->equipment->id,
            $l->equipment->name,
            $l->event ? ' — '.$l->event->title : ($l->loan_reason ? ' — '.$l->loan_reason : '')
        ));

        Mail::raw(
            __("Hello :name,\n\nThe following equipment has been loaned to you:\n\n:items\n\nPlease return the equipment after use.\n\nThank you!", [
                'name' => $user->detail?->first_name ?? $user->name,
                'items' => $lines->map(fn ($l) => '• '.$l)->implode("\n"),
            ]),
            fn ($m) => $m->to($user->primary_email)->subject(__('Equipment loaned to you'))
        );

        $loans->each(fn ($l) => $l->update(['loan_email_sent_at' => now()]));
    }
}
