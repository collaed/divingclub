<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\SendEventAutomationEmail;
use App\Models\Event;
use App\Models\EventAutomationRule;
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
}
