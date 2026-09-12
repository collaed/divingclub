<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendEventAutomationEmail;
use App\Models\Event;
use App\Models\EventAutomationRule;
use Illuminate\Support\Collection;

/**
 * Evaluated once per event, when registrations close (EvaluateEventAutomation
 * console command). Each rule either comes from the event itself (an
 * override) or, failing that, from its season_pattern (the default applied
 * to every event the pattern generates) — see resolveRules().
 */
class EventAutomationService
{
    /** @return Collection<int, EventAutomationRule> */
    public function resolveRules(Event $event): Collection
    {
        $eventRules = EventAutomationRule::where('event_id', $event->id)->get();
        $overriddenTypes = $eventRules->pluck('rule_type');

        $patternRules = $event->season_pattern_id
            ? EventAutomationRule::where('season_pattern_id', $event->season_pattern_id)
                ->whereNotIn('rule_type', $overriddenTypes->all())
                ->get()
            : collect();

        return $eventRules->concat($patternRules);
    }

    /** Idempotent — a second call on an already-evaluated event is a no-op. */
    public function evaluate(Event $event): void
    {
        if ($event->automation_evaluated_at !== null) {
            return;
        }

        foreach ($this->resolveRules($event) as $rule) {
            match ($rule->rule_type) {
                EventAutomationRule::TYPE_MIN_REGISTRATIONS => $this->evaluateMinRegistrations($event, $rule),
                EventAutomationRule::TYPE_REQUIRES_LIFEGUARD => $this->evaluateRequiresLifeguard($event, $rule),
                default => null,
            };
        }

        $event->update(['automation_evaluated_at' => now()]);
    }

    private function evaluateMinRegistrations(Event $event, EventAutomationRule $rule): void
    {
        if ($event->confirmedRegistrations()->count() >= (int) $rule->threshold) {
            return;
        }

        $recipients = array_unique(array_merge($this->participantEmails($event), $rule->extraRecipientList()));
        $this->sendEmail($rule, $event, $recipients);

        if ($rule->cancels_event) {
            $event->update(['status' => 'cancelled', 'inscriptions_closed' => true]);
        }
    }

    private function evaluateRequiresLifeguard(Event $event, EventAutomationRule $rule): void
    {
        $hasLifeguard = $event->confirmedRegistrations()
            ->whereHas('user.detail', fn ($q) => $q->where('is_lifeguard', true))
            ->exists();

        if ($hasLifeguard) {
            return;
        }

        // Organizer-facing alert (nobody signed up is lifeguard-qualified —
        // actionable for whoever runs the session), not a participant-wide
        // notice like the headcount rule.
        $recipients = array_unique(array_filter([$event->responsible?->primary_email, ...$rule->extraRecipientList()]));
        $this->sendEmail($rule, $event, $recipients);

        if ($rule->cancels_event) {
            $event->update(['status' => 'cancelled', 'inscriptions_closed' => true]);
        }
    }

    /** @return array<int, string> */
    private function participantEmails(Event $event): array
    {
        return $event->confirmedRegistrations()->with('user')->get()
            ->pluck('user.primary_email')->filter()->unique()->values()->all();
    }

    /** @param  array<int, string>  $recipients */
    private function sendEmail(EventAutomationRule $rule, Event $event, array $recipients): void
    {
        if ($recipients === []) {
            return;
        }

        SendEventAutomationEmail::dispatch($rule->id, $event->id, $recipients)->afterCommit();
    }
}
