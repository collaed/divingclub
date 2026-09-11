<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\EventAutomationRule;
use App\Models\Season;
use App\Models\SeasonPattern;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

#[Group('p1')]
class EventAutomationRuleTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_bureau_can_create_a_pattern_level_rule(): void
    {
        $pattern = SeasonPattern::create(['season_id' => Season::factory()->create()->id, 'day_of_week' => 1, 'start_time' => '19:00', 'title' => 'Fosse']);

        $this->actingAs($this->createBureauUser())
            ->post(route('admin.event-automation-rules.store'), [
                'target' => 'pattern',
                'season_pattern_id' => $pattern->id,
                'rule_type' => EventAutomationRule::TYPE_MIN_REGISTRATIONS,
                'threshold' => 4,
                'cancels_event' => '1',
            ])
            ->assertRedirect();

        $rule = EventAutomationRule::firstOrFail();
        $this->assertSame($pattern->id, $rule->season_pattern_id);
        $this->assertNull($rule->event_id);
        $this->assertSame(4, $rule->threshold);
        $this->assertTrue($rule->cancels_event);
    }

    public function test_bureau_can_create_an_event_level_rule_without_a_threshold(): void
    {
        $event = Event::factory()->create();

        $this->actingAs($this->createBureauUser())
            ->post(route('admin.event-automation-rules.store'), [
                'target' => 'event',
                'event_id' => $event->id,
                'rule_type' => EventAutomationRule::TYPE_REQUIRES_LIFEGUARD,
                'extra_recipients' => 'safety@clubcep.eu',
            ])
            ->assertRedirect();

        $rule = EventAutomationRule::firstOrFail();
        $this->assertSame($event->id, $rule->event_id);
        $this->assertSame('safety@clubcep.eu', $rule->extra_recipients);
    }

    public function test_min_registrations_requires_a_threshold(): void
    {
        $event = Event::factory()->create();

        $this->actingAs($this->createBureauUser())
            ->post(route('admin.event-automation-rules.store'), [
                'target' => 'event',
                'event_id' => $event->id,
                'rule_type' => EventAutomationRule::TYPE_MIN_REGISTRATIONS,
            ])
            ->assertSessionHasErrors('threshold');
    }

    public function test_bureau_can_delete_a_rule(): void
    {
        $rule = EventAutomationRule::create(['event_id' => Event::factory()->create()->id, 'rule_type' => EventAutomationRule::TYPE_REQUIRES_LIFEGUARD]);

        $this->actingAs($this->createBureauUser())
            ->delete(route('admin.event-automation-rules.destroy', $rule))
            ->assertRedirect();

        $this->assertDatabaseMissing('event_automation_rules', ['id' => $rule->id]);
    }

    public function test_a_regular_member_is_forbidden(): void
    {
        $this->actingAs($this->createMemberUser())
            ->get(route('admin.event-automation-rules.index'))
            ->assertForbidden();
    }
}
