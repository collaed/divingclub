<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventAutomationRule;
use App\Models\EventAutomationRuleFire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Automation rules are one-shot per event (automation_evaluated_at for
 * registration-close rules, event_automation_rule_fires for hours-before
 * rules). Editing an event's schedule must re-arm them, otherwise a
 * rescheduled event's rules silently never fire again — caught live on
 * staging, where a "cancel if under threshold" rule never fired after the
 * event was moved.
 */
#[Group('p1')]
class EventAutomationRearmTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config(['club.timezone' => 'Europe/Luxembourg']);
    }

    private function eventWithSpentRules(): array
    {
        $event = Event::create([
            'title' => 'Essai', 'event_type' => 'training', 'event_date' => '2026-09-19', 'event_time' => '21:30:00',
            'inscription_close_at' => '2026-09-19 12:00:00', 'automation_evaluated_at' => now(),
        ]);
        $rule = EventAutomationRule::create([
            'event_id' => $event->id, 'rule_type' => EventAutomationRule::TYPE_MIN_REGISTRATIONS,
            'trigger' => EventAutomationRule::TRIGGER_HOURS_BEFORE_EVENT, 'hours_before_event' => 1, 'threshold' => 4,
        ]);
        EventAutomationRuleFire::create(['event_id' => $event->id, 'event_automation_rule_id' => $rule->id, 'fired_at' => now()]);

        return [$event, $rule];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Essai', 'event_type' => 'training',
            'event_date' => '2026-09-19', 'event_time' => '21:30',
            'inscription_close_at' => '2026-09-19T14:00',
        ], $overrides);
    }

    public function test_moving_the_close_time_rearms_registration_close_rules(): void
    {
        [$event] = $this->eventWithSpentRules();

        $this->actingAs($this->createBureauUser())->put(route('events.update', $event), $this->payload(['inscription_close_at' => '2026-09-20T10:00']))->assertRedirect();

        $this->assertNull($event->refresh()->automation_evaluated_at);
    }

    public function test_moving_the_start_rearms_hours_before_rules(): void
    {
        [$event, $rule] = $this->eventWithSpentRules();

        $this->actingAs($this->createBureauUser())->put(route('events.update', $event), $this->payload(['event_date' => '2026-09-21']))->assertRedirect();

        $this->assertDatabaseMissing('event_automation_rule_fires', ['event_id' => $event->id, 'event_automation_rule_id' => $rule->id]);
    }

    public function test_saving_without_changing_the_schedule_keeps_spent_rules_spent(): void
    {
        [$event, $rule] = $this->eventWithSpentRules();

        // 14:00 local = 12:00 UTC, the stored close time; "21:30" vs stored "21:30:00".
        $this->actingAs($this->createBureauUser())->put(route('events.update', $event), $this->payload(['title' => 'Essai renamed']))->assertRedirect();

        $this->assertNotNull($event->refresh()->automation_evaluated_at);
        $this->assertDatabaseHas('event_automation_rule_fires', ['event_id' => $event->id, 'event_automation_rule_id' => $rule->id]);
    }
}
