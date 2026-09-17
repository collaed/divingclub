<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventAutomationRule;
use App\Services\EventAutomationService;
use Illuminate\Console\Command;

/**
 * Runs the configured automation rules (see EventAutomationRule) for every
 * event that could plausibly have a rule due: registration just closed, or
 * an hours_before_event rule's checkpoint may have been reached. The actual
 * due check (and idempotency) lives in EventAutomationService::evaluate(),
 * so this query only needs to narrow down candidates — running it more
 * often, or over a broader candidate set, than strictly necessary is
 * harmless.
 */
class EvaluateEventAutomation extends Command
{
    protected $signature = 'events:evaluate-automation';

    protected $description = 'Evaluate automation rules for events whose registrations just closed or that are approaching an hours-before-event checkpoint';

    public function handle(EventAutomationService $service): int
    {
        $events = Event::where('status', '!=', 'cancelled')
            ->where(function ($query) {
                $query->where(function ($registrationClose) {
                    $registrationClose->whereNotNull('inscription_close_at')
                        ->where('inscription_close_at', '<=', now())
                        ->whereNull('automation_evaluated_at');
                })->orWhere(function ($hoursBefore) {
                    // Idempotency for this branch is per-rule (EventAutomationRuleFire),
                    // not a single event-level flag, so it can't be checked here — the
                    // date floor just keeps this from re-scanning years of old events
                    // whose rules (if any) have long since fired or been skipped.
                    $hoursBefore->where('event_date', '>=', now()->subWeek())
                        ->where(function ($hasHoursRule) {
                            $hasHoursRule->whereHas(
                                'automationRules',
                                fn ($r) => $r->where('trigger', EventAutomationRule::TRIGGER_HOURS_BEFORE_EVENT)
                            )->orWhereHas(
                                'seasonPattern.automationRules',
                                fn ($r) => $r->where('trigger', EventAutomationRule::TRIGGER_HOURS_BEFORE_EVENT)
                            );
                        });
                });
            })
            ->get();

        foreach ($events as $event) {
            $service->evaluate($event);
        }

        $this->info(count($events).' event(s) checked.');

        return self::SUCCESS;
    }
}
