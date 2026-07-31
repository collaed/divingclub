<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Vote;
use App\Services\PushNotificationService;
use App\Services\ScheduleHeartbeat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class AutoOpenCloseVotes implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        $opened = Vote::where('status', 'draft')->where('opens_at', '<=', now())->get();
        foreach ($opened as $vote) {
            $vote->update(['status' => 'open']);
            app(PushNotificationService::class)->sendToAll(
                __('Vote Open'),
                $vote->title,
                route('vote.show', ['token' => 'check'])
            );
        }
        Vote::where('status', 'open')->where('closes_at', '<=', now())->update(['status' => 'closed']);

        ScheduleHeartbeat::beat('vote-auto', $opened->count() ? "Opened {$opened->count()} vote(s)" : null);
    }
}
