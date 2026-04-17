<?php

namespace App\Jobs;

use App\Models\EquipmentLoan;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendReturnRecapEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $userId) {}

    public function handle(): void
    {
        $user = User::with('detail')->find($this->userId);
        if (! $user?->primary_email) {
            return;
        }

        // Recently returned (not yet emailed)
        $returned = EquipmentLoan::with('equipment')
            ->where('user_id', $this->userId)
            ->whereNotNull('returned_at')
            ->whereNull('return_email_sent_at')
            ->get();

        // Still outstanding
        $outstanding = EquipmentLoan::with('equipment')
            ->where('user_id', $this->userId)
            ->whereNull('returned_at')
            ->get();

        if ($returned->isEmpty() && $outstanding->isEmpty()) {
            return;
        }

        $formatItem = fn ($l) => sprintf('%s #%s (%s)', ucfirst($l->equipment->type), $l->equipment->short_number ?? $l->equipment->id, $l->equipment->name);

        $body = __("Hello :name,\n\n", ['name' => $user->detail?->first_name ?? $user->name]);

        if ($returned->isNotEmpty()) {
            $body .= __("Returned:\n:items\n\n", [
                'items' => $returned->map(fn ($l) => '✓ '.$formatItem($l))->implode("\n"),
            ]);
        }

        if ($outstanding->isNotEmpty()) {
            $body .= __("Still on loan:\n:items\n\nPlease return at your earliest convenience.", [
                'items' => $outstanding->map(fn ($l) => '• '.$formatItem($l))->implode("\n"),
            ]);
        } else {
            $body .= __('All equipment has been returned. Thank you!');
        }

        Mail::raw($body, fn ($m) => $m->to($user->primary_email)->subject(
            $outstanding->isEmpty()
                ? __('All equipment returned — thank you!')
                : __('Equipment return update')
        ));

        $returned->each(fn ($l) => $l->update(['return_email_sent_at' => now()]));
    }
}
