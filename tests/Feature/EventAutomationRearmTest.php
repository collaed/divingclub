<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendEventAutomationEmail;
use App\Models\Event;
use App\Models\EventAutomationRule;
use App\Models\EventAutomationRuleFire;
use App\Services\EventAutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * A fire record is keyed by (event, rule, the instant the rule was due), so
 * rescheduling an event makes its rules due again with no explicit re-arming,
 * an unchanged event never double-fires, and a reschedule to a point already
 * covered by an earlier fire stays handled. Caught live on staging, where a
 * "cancel if under threshold" rule never fired after the event was moved,
 * because the old one-shot flag survived the reschedule.
 */
#[Group('p1')]
class EventAutomationRearmTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['club.timezone' => 'UTC']);
        Bus::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function evaluate(Event $event): void
    {
        app(EventAutomationService::class)->evaluate($event->fresh());
    }

    private function hoursBeforeRule(Event $event, int $hours = 1): EventAutomationRule
    {
        return EventAutomationRule::create([
            'event_id' => $event->id, 'rule_type' => EventAutomationRule::TYPE_MIN_REGISTRATIONS,
            'trigger' => EventAutomationRule::TRIGGER_HOURS_BEFORE_EVENT, 'hours_before_event' => $hours,
            'threshold' => 5, 'extra_recipients' => 'chief@clubcep.eu',
        ]);
    }

    public function test_an_unchanged_event_never_double_fires(): void
    {
        Carbon::setTestNow('2026-09-19 20:00:00');
        $event = Event::factory()->create(['status' => 'scheduled', 'event_date' => '2026-09-19', 'event_time' => '21:00:00']);
        $this->hoursBeforeRule($event);

        $this->evaluate($event);
        $this->evaluate($event);

        Bus::assertDispatchedTimes(SendEventAutomationEmail::class, 1);
    }

    public function test_moving_the_start_later_makes_an_hours_before_rule_due_again(): void
    {
        Carbon::setTestNow('2026-09-19 20:00:00');
        $event = Event::factory()->create(['status' => 'scheduled', 'event_date' => '2026-09-19', 'event_time' => '21:00:00']);
        $rule = $this->hoursBeforeRule($event);
        $this->evaluate($event);

        $event->update(['event_date' => '2026-09-20']);
        Carbon::setTestNow('2026-09-20 20:00:00');
        $this->evaluate($event);

        Bus::assertDispatchedTimes(SendEventAutomationEmail::class, 2);
        $this->assertSame(2, EventAutomationRuleFire::where('event_automation_rule_id', $rule->id)->count());
    }

    public function test_moving_the_start_earlier_into_what_already_fired_stays_handled(): void
    {
        Carbon::setTestNow('2026-09-19 20:00:00');
        $event = Event::factory()->create(['status' => 'scheduled', 'event_date' => '2026-09-19', 'event_time' => '21:00:00']);
        $this->hoursBeforeRule($event);
        $this->evaluate($event);

        $event->update(['event_time' => '20:30:00']);
        $this->evaluate($event);

        Bus::assertDispatchedTimes(SendEventAutomationEmail::class, 1);
    }

    public function test_moving_the_close_time_later_makes_a_registration_close_rule_due_again(): void
    {
        Carbon::setTestNow('2026-09-19 13:00:00');
        $event = Event::factory()->create([
            'status' => 'scheduled', 'event_date' => '2026-09-25', 'event_time' => '21:00:00',
            'inscription_close_at' => '2026-09-19 12:00:00',
        ]);
        EventAutomationRule::create([
            'event_id' => $event->id, 'rule_type' => EventAutomationRule::TYPE_MIN_REGISTRATIONS,
            'threshold' => 5, 'cancels_event' => true, 'extra_recipients' => 'chief@clubcep.eu',
        ]);
        $this->evaluate($event);
        $event->update(['status' => 'scheduled']);

        $event->update(['inscription_close_at' => '2026-09-19 14:00:00']);
        Carbon::setTestNow('2026-09-19 14:30:00');
        $this->evaluate($event);

        Bus::assertDispatchedTimes(SendEventAutomationEmail::class, 2);
    }

    public function test_a_registration_close_rule_for_an_event_that_already_started_is_recorded_without_running(): void
    {
        Carbon::setTestNow('2026-09-19 20:00:00');
        $event = Event::factory()->create([
            'status' => 'scheduled', 'event_date' => '2026-09-19', 'event_time' => '10:00:00',
            'inscription_close_at' => '2026-09-18 12:00:00',
        ]);
        $rule = EventAutomationRule::create([
            'event_id' => $event->id, 'rule_type' => EventAutomationRule::TYPE_MIN_REGISTRATIONS,
            'threshold' => 5, 'cancels_event' => true, 'extra_recipients' => 'chief@clubcep.eu',
        ]);

        $this->evaluate($event);

        Bus::assertNotDispatched(SendEventAutomationEmail::class);
        $this->assertSame('scheduled', $event->fresh()->status);
        $this->assertDatabaseHas('event_automation_rule_fires', ['event_id' => $event->id, 'event_automation_rule_id' => $rule->id]);
    }
}
