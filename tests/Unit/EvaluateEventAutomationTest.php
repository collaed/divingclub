<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\SendEventAutomationEmail;
use App\Models\Event;
use App\Models\EventAutomationRule;
use App\Models\EventAutomationRuleFire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('p1')]
class EvaluateEventAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_events_with_a_past_unevaluated_close_time_are_picked_up(): void
    {
        Bus::fake();

        $due = Event::factory()->create(['inscription_close_at' => now()->subHour(), 'automation_evaluated_at' => null, 'status' => 'scheduled']);
        EventAutomationRule::create(['event_id' => $due->id, 'rule_type' => EventAutomationRule::TYPE_MIN_REGISTRATIONS, 'threshold' => 5, 'extra_recipients' => 'bureau@clubcep.eu']);

        $notYetClosed = Event::factory()->create(['inscription_close_at' => now()->addHour(), 'automation_evaluated_at' => null]);
        $alreadyEvaluated = Event::factory()->create(['inscription_close_at' => now()->subHour(), 'automation_evaluated_at' => now()]);
        $noCloseDate = Event::factory()->create(['inscription_close_at' => null]);

        Artisan::call('events:evaluate-automation');

        $this->assertNotNull($due->fresh()->automation_evaluated_at);
        $this->assertNull($notYetClosed->fresh()->automation_evaluated_at);
        Bus::assertDispatched(SendEventAutomationEmail::class);
    }

    public function test_events_with_a_due_hours_before_event_rule_are_picked_up(): void
    {
        Bus::fake();

        $due = Event::factory()->create([
            'status' => 'scheduled',
            'event_date' => now()->addHour()->toDateString(),
            'event_time' => now()->addHour()->format('H:i'),
        ]);
        $rule = EventAutomationRule::create([
            'event_id' => $due->id, 'rule_type' => EventAutomationRule::TYPE_MIN_REGISTRATIONS,
            'trigger' => EventAutomationRule::TRIGGER_HOURS_BEFORE_EVENT, 'hours_before_event' => 3,
            'threshold' => 5, 'extra_recipients' => 'bureau@clubcep.eu',
        ]);

        // No automation rules at all — must not be swept in.
        $unrelated = Event::factory()->create(['status' => 'scheduled']);

        Artisan::call('events:evaluate-automation');

        $this->assertDatabaseHas('event_automation_rule_fires', ['event_id' => $due->id, 'event_automation_rule_id' => $rule->id]);
        $this->assertSame(0, EventAutomationRuleFire::where('event_id', $unrelated->id)->count());
        Bus::assertDispatched(SendEventAutomationEmail::class);
    }

    public function test_an_event_older_than_a_week_is_not_swept_for_hours_before_event_rules(): void
    {
        Bus::fake();

        $old = Event::factory()->create([
            'status' => 'scheduled',
            'event_date' => now()->subWeeks(2)->toDateString(),
            'event_time' => now()->subWeeks(2)->format('H:i'),
        ]);
        EventAutomationRule::create([
            'event_id' => $old->id, 'rule_type' => EventAutomationRule::TYPE_MIN_REGISTRATIONS,
            'trigger' => EventAutomationRule::TRIGGER_HOURS_BEFORE_EVENT, 'hours_before_event' => 3,
            'threshold' => 99, 'extra_recipients' => 'bureau@clubcep.eu',
        ]);

        Artisan::call('events:evaluate-automation');

        $this->assertSame(0, EventAutomationRuleFire::where('event_id', $old->id)->count());
        Bus::assertNotDispatched(SendEventAutomationEmail::class);
    }

    /**
     * Regression: an event with a due hours_before_event rule matches the
     * candidate query's OR branch for that trigger — evaluate() must not
     * then also assume its registration_close rule is due, since the query
     * never checked inscription_close_at for this event at all. Caught live
     * on staging: it cancelled a test event ~2.5h before its real close time.
     */
    public function test_an_event_due_for_an_hours_before_rule_does_not_also_fire_its_not_yet_due_registration_close_rule(): void
    {
        Bus::fake();

        $event = Event::factory()->create([
            'status' => 'scheduled',
            'event_date' => now()->addHour()->toDateString(),
            'event_time' => now()->addHour()->format('H:i'),
            'inscription_close_at' => now()->addHours(2),
        ]);
        EventAutomationRule::create([
            'event_id' => $event->id, 'rule_type' => EventAutomationRule::TYPE_REQUIRES_LIFEGUARD,
            'trigger' => EventAutomationRule::TRIGGER_HOURS_BEFORE_EVENT, 'hours_before_event' => 3,
        ]);
        $closeRule = EventAutomationRule::create([
            'event_id' => $event->id, 'rule_type' => EventAutomationRule::TYPE_MIN_REGISTRATIONS,
            'threshold' => 5, 'cancels_event' => true, 'extra_recipients' => 'bureau@clubcep.eu',
        ]);

        Artisan::call('events:evaluate-automation');

        $event->refresh();
        $this->assertNull($event->automation_evaluated_at);
        $this->assertSame('scheduled', $event->status);
        Bus::assertNotDispatched(SendEventAutomationEmail::class, fn (SendEventAutomationEmail $job): bool => $job->ruleId === $closeRule->id);
    }
}
