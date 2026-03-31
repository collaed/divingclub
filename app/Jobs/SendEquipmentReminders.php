<?php

namespace App\Jobs;

use App\Models\EquipmentLoan;
use App\Models\ThemeSetting;
use App\Services\PushNotificationService;
use App\Services\ScheduleHeartbeat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendEquipmentReminders implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        $thresholdDays = (int) ThemeSetting::get('equipment_loan_max_days', 30);

        $overdueLoans = EquipmentLoan::whereNull('returned_at')
            ->whereNotNull('expected_return_date')
            ->where('expected_return_date', '<', now())
            ->whereNull('reminder_sent_at')
            ->with(['user', 'equipment'])
            ->get();

        foreach ($overdueLoans as $loan) {
            Log::info("Overdue loan: {$loan->equipment->name} → {$loan->user->name}");
            app(PushNotificationService::class)->sendToUser(
                $loan->user,
                __('Equipment Return Overdue'),
                __(':item was due back :date. Please return it.', [
                    'item' => $loan->equipment->name,
                    'date' => $loan->expected_return_date->format('d/m/Y'),
                ]),
                '/profile'
            );
            $loan->update(['reminder_sent_at' => now()]);
        }

        EquipmentLoan::whereNull('returned_at')
            ->whereNull('expected_return_date')
            ->where('loaned_at', '<', now()->subDays($thresholdDays))
            ->whereNull('reminder_sent_at')
            ->with(['user', 'equipment'])
            ->each(function ($loan) {
                app(PushNotificationService::class)->sendToBureau(
                    __('Long Equipment Loan'),
                    __(':item loaned to :name on :date', [
                        'item' => $loan->equipment->name,
                        'name' => $loan->user->name,
                        'date' => $loan->loaned_at->format('d/m/Y'),
                    ]),
                    '/admin/equipment/'.$loan->equipment_id
                );
                $loan->update(['reminder_sent_at' => now()]);
            });

        ScheduleHeartbeat::beat('equipment-reminders', $overdueLoans->count() ? "{$overdueLoans->count()} overdue" : null);
    }
}
