<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Document;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendMedicalReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $today = now()->startOfDay();

        foreach ([30, 15, 7, 0] as $days) {
            $targetDate = $today->copy()->addDays($days);
            $sentColumn = "reminder_{$days}_sent_at";

            $certs = Document::where('category', 'medical')
                ->where('is_current', true)
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', $targetDate)
                ->whereNull($sentColumn)
                ->with('user.detail')
                ->get();

            foreach ($certs as $cert) {
                Log::info("Medical reminder ({$days}d): {$cert->user->name} — cert expires {$cert->expiry_date->format('d/m/Y')}");

                // Push notification to the member
                app(PushNotificationService::class)->sendToUser(
                    $cert->user,
                    __('Medical Certificate Expiring'),
                    $days > 0
                        ? __('Your medical certificate expires in :days days.', ['days' => $days])
                        : __('Your medical certificate has expired.'),
                    '/profile#medical'
                );

                $cert->update([$sentColumn => $today]);
            }
        }
    }
}
