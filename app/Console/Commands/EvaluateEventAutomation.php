<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\EventAutomationService;
use Illuminate\Console\Command;

/**
 * Runs the configured automation rules (see EventAutomationRule) for every
 * event whose registration window has just closed. `automation_evaluated_at`
 * makes this idempotent, so running it more often than necessary is harmless.
 */
class EvaluateEventAutomation extends Command
{
    protected $signature = 'events:evaluate-automation';

    protected $description = 'Evaluate automation rules for events whose registrations just closed';

    public function handle(EventAutomationService $service): int
    {
        $events = Event::whereNotNull('inscription_close_at')
            ->where('inscription_close_at', '<=', now())
            ->whereNull('automation_evaluated_at')
            ->where('status', '!=', 'cancelled')
            ->get();

        foreach ($events as $event) {
            $service->evaluate($event);
        }

        $this->info(count($events).' event(s) evaluated.');

        return self::SUCCESS;
    }
}
