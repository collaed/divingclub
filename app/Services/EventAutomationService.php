<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendEventAutomationEmail;
use App\Models\Event;
use App\Models\EventAutomationRule;
use App\Models\EventAutomationRuleFire;
use Carbon\Carbon;
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

    /**
     * Rules with the default trigger — due once registrations have actually
     * closed. A rule for an event that has already started is recorded as
     * fired without running: there's nothing left to check or act on.
     */
    private function evaluateRegistrationClose(Event $event): void
    {
        if (! $event->inscription_close_at || $event->inscription_close_at->isFuture()) {
            return;
        }

        $closeAt = $event->inscription_close_at->copy()->utc();
        $started = $event->startsAt()?->isPast() ?? false;

        foreach ($this->resolveRules($event)->where('trigger', EventAutomationRule::TRIGGER_REGISTRATION_CLOSE) as $rule) {
            if ($this->alreadyFired($event, $rule, $closeAt)) {
                continue;
            }

            if (! $started) {
                $this->runRule($event, $rule);
            }
            $this->recordFire($event, $rule, $closeAt);
        }
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

        foreach ($rules as $rule) {
            $checkpoint = $startsAt->copy()->subHours((int) $rule->hours_before_event)->utc();

            if ($startsAt->isFuture() && $checkpoint->isFuture()) {
                continue;
            }
            if ($this->alreadyFired($event, $rule, $checkpoint)) {
                continue;
            }

            if ($startsAt->isFuture()) {
                $this->runRule($event, $rule);
            }
            $this->recordFire($event, $rule, $checkpoint);
        }
    }

    /**
     * A rule counts as already handled for a due instant if it fired for
     * exactly that instant, or fired at/after it. Rescheduling the event
     * moves the instant: moved later than the last fire, the rule is due
     * again (no explicit re-arming needed); moved earlier, into the past
     * relative to what already fired, it stays handled.
     */
    private function alreadyFired(Event $event, EventAutomationRule $rule, Carbon $dueAt): bool
    {
        return EventAutomationRuleFire::where('event_id', $event->id)
            ->where('event_automation_rule_id', $rule->id)
            ->where(fn ($q) => $q->where('scheduled_for', $dueAt)->orWhere('fired_at', '>=', $dueAt))
            ->exists();
    }

    private function recordFire(Event $event, EventAutomationRule $rule, Carbon $dueAt): void
    {
        EventAutomationRuleFire::create([
            'event_id' => $event->id, 'event_automation_rule_id' => $rule->id,
            'scheduled_for' => $dueAt, 'fired_at' => now(),
        ]);
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
