<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\SendEventAutomationEmail;
use App\Models\Event;
use App\Models\EventAutomationRule;
use App\Models\EventRegistration;
use App\Models\MemberDetail;
use App\Models\Season;
use App\Models\SeasonPattern;
use App\Models\User;
use App\Services\EventAutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('p1')]
class EventAutomationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function pattern(): SeasonPattern
    {
        return SeasonPattern::create(['season_id' => Season::factory()->create()->id, 'day_of_week' => 1, 'start_time' => '19:00', 'title' => 'Fosse Apnée']);
    }

    private function confirmedParticipant(Event $event, bool $lifeguard = false): User
    {
        $user = User::factory()->create();
        MemberDetail::factory()->create(['user_id' => $user->id, 'is_lifeguard' => $lifeguard]);
        EventRegistration::factory()->create(['event_id' => $event->id, 'user_id' => $user->id, 'status' => 'confirmed']);

        return $user;
    }

    public function test_an_event_level_rule_overrides_the_patterns_rule_of_the_same_type(): void
    {
        $pattern = $this->pattern();
        $event = Event::factory()->create(['season_pattern_id' => $pattern->id]);

        EventAutomationRule::create(['season_pattern_id' => $pattern->id, 'rule_type' => EventAutomationRule::TYPE_MIN_REGISTRATIONS, 'threshold' => 10]);
        $override = EventAutomationRule::create(['event_id' => $event->id, 'rule_type' => EventAutomationRule::TYPE_MIN_REGISTRATIONS, 'threshold' => 2]);
        $unrelated = EventAutomationRule::create(['season_pattern_id' => $pattern->id, 'rule_type' => EventAutomationRule::TYPE_REQUIRES_LIFEGUARD]);

        $resolved = app(EventAutomationService::class)->resolveRules($event);

        $this->assertTrue($resolved->contains('id', $override->id));
        $this->assertTrue($resolved->contains('id', $unrelated->id));
        $this->assertSame(2, $resolved->count());
    }

    public function test_below_threshold_emails_participants_and_extra_recipients_and_cancels(): void
    {
        Bus::fake();
        $event = Event::factory()->create(['status' => 'scheduled']);
        $rule = EventAutomationRule::create([
            'event_id' => $event->id, 'rule_type' => EventAutomationRule::TYPE_MIN_REGISTRATIONS,
            'threshold' => 3, 'cancels_event' => true, 'extra_recipients' => 'chief@clubcep.eu',
        ]);
        $participant = $this->confirmedParticipant($event);

        app(EventAutomationService::class)->evaluate($event);

        $event->refresh();
        $this->assertSame('cancelled', $event->status);
        $this->assertTrue($event->inscriptions_closed);
        $this->assertNotNull($event->automation_evaluated_at);
        Bus::assertDispatched(SendEventAutomationEmail::class, fn ($job) => $job->ruleId === $rule->id
            && in_array($participant->primary_email, $job->recipients, true)
            && in_array('chief@clubcep.eu', $job->recipients, true));
    }

    public function test_at_or_above_threshold_does_not_email_or_cancel(): void
    {
        Bus::fake();
        $event = Event::factory()->create(['status' => 'scheduled']);
        EventAutomationRule::create(['event_id' => $event->id, 'rule_type' => EventAutomationRule::TYPE_MIN_REGISTRATIONS, 'threshold' => 1, 'cancels_event' => true]);
        $this->confirmedParticipant($event);

        app(EventAutomationService::class)->evaluate($event);

        $event->refresh();
        $this->assertSame('scheduled', $event->status);
        Bus::assertNotDispatched(SendEventAutomationEmail::class);
    }

    public function test_no_lifeguard_among_participants_emails_the_responsible_not_every_participant(): void
    {
        Bus::fake();
        $responsible = User::factory()->create();
        $event = Event::factory()->create(['responsible_id' => $responsible->id]);
        EventAutomationRule::create(['event_id' => $event->id, 'rule_type' => EventAutomationRule::TYPE_REQUIRES_LIFEGUARD]);
        $participant = $this->confirmedParticipant($event, lifeguard: false);

        app(EventAutomationService::class)->evaluate($event);

        Bus::assertDispatched(SendEventAutomationEmail::class, function ($job) use ($responsible, $participant) {
            return in_array($responsible->primary_email, $job->recipients, true)
                && ! in_array($participant->primary_email, $job->recipients, true);
        });
    }

    public function test_a_lifeguard_among_participants_means_no_email(): void
    {
        Bus::fake();
        $event = Event::factory()->create(['responsible_id' => User::factory()->create()->id]);
        EventAutomationRule::create(['event_id' => $event->id, 'rule_type' => EventAutomationRule::TYPE_REQUIRES_LIFEGUARD]);
        $this->confirmedParticipant($event, lifeguard: true);

        app(EventAutomationService::class)->evaluate($event);

        Bus::assertNotDispatched(SendEventAutomationEmail::class);
    }

    public function test_evaluate_is_idempotent(): void
    {
        Bus::fake();
        $event = Event::factory()->create(['automation_evaluated_at' => now()->subHour(), 'status' => 'scheduled']);
        EventAutomationRule::create(['event_id' => $event->id, 'rule_type' => EventAutomationRule::TYPE_MIN_REGISTRATIONS, 'threshold' => 99, 'cancels_event' => true]);

        app(EventAutomationService::class)->evaluate($event);

        $event->refresh();
        $this->assertSame('scheduled', $event->status);
        Bus::assertNotDispatched(SendEventAutomationEmail::class);
    }
}
