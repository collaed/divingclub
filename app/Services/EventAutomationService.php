<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendEventAutomationEmail;
use App\Models\Event;
use App\Models\EventAutomationRule;
use App\Models\EventAutomationRuleFire;
use Illuminate\Support\Collection;

/**
 * Evaluated per event by the EvaluateEventAutomation console command, once
 * its rules become due — either when registrations close, or a fixed number
 * of hours before the event starts, depending on each rule's trigger. Each
 * rule either comes from the event itself (an override) or, failing that,
 * from its season_pattern (the default applied to every event the pattern
 * generates) — see resolveRules().
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

    /**
     * Runs both trigger passes, each independently idempotent and each
     * checking its own due time — safe to call on any event regardless of
     * why the caller (EvaluateEventAutomation) selected it as a candidate.
     * That query's candidate set is intentionally broad (a single OR across
     * both triggers), so evaluate() must never assume a candidate is due
     * for one trigger just because it matched the query for the other.
     */
    public function evaluate(Event $event): void
    {
        $this->evaluateRegistrationClose($event);
        $this->evaluateHoursBeforeEvent($event);
    }

    /** Rules with the default trigger — due once registrations have actually closed. */
    private function evaluateRegistrationClose(Event $event): void
    {
        if ($event->automation_evaluated_at !== null) {
            return;
        }
        if (! $event->inscription_close_at || $event->inscription_close_at->isFuture()) {
            return;
        }

        $rules = $this->resolveRules($event)->where('trigger', EventAutomationRule::TRIGGER_REGISTRATION_CLOSE);
        foreach ($rules as $rule) {
            $this->runRule($event, $rule);
        }

        $event->update(['automation_evaluated_at' => now()]);
    }

    /**
     * Rules due a fixed number of hours before the event starts, independent
     * of the registration window. Each rule is gated and fired on its own —
     * necessary because one event can have several such rules (one per
     * rule_type) with different hours_before_event offsets. A rule for an
     * event whose start has already passed is marked fired without running,
     * since there's nothing left to check or warn about beforehand.
     */
    private function evaluateHoursBeforeEvent(Event $event): void
    {
        $rules = $this->resolveRules($event)->where('trigger', EventAutomationRule::TRIGGER_HOURS_BEFORE_EVENT);
        if ($rules->isEmpty()) {
            return;
        }

        $startsAt = $event->startsAt();
        if (! $startsAt) {
            return;
        }

        $firedRuleIds = EventAutomationRuleFire::where('event_id', $event->id)->pluck('event_automation_rule_id');

        foreach ($rules as $rule) {
            if ($firedRuleIds->contains($rule->id)) {
                continue;
            }

            if ($startsAt->isFuture() && $startsAt->copy()->subHours((int) $rule->hours_before_event)->isFuture()) {
                continue;
            }

            if ($startsAt->isFuture()) {
                $this->runRule($event, $rule);
            }

            EventAutomationRuleFire::create(['event_id' => $event->id, 'event_automation_rule_id' => $rule->id, 'fired_at' => now()]);
        }
    }

    private function runRule(Event $event, EventAutomationRule $rule): void
    {
        match ($rule->rule_type) {
            EventAutomationRule::TYPE_MIN_REGISTRATIONS => $this->evaluateMinRegistrations($event, $rule),
            EventAutomationRule::TYPE_REQUIRES_LIFEGUARD => $this->evaluateRequiresLifeguard($event, $rule),
            default => null,
        };
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
