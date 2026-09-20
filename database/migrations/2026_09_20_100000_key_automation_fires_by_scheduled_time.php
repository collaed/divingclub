<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\EventAutomationRule;
use App\Services\EventAutomationService;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A fire record is now keyed by (event, rule, scheduled_for) — the
     * instant the rule was due — instead of (event, rule) plus a separate
     * events.automation_evaluated_at flag for registration-close rules.
     * Rescheduling an event changes the computed instant, so the old record
     * simply stops matching and the rule can fire again, without any
     * explicit "re-arm" step, and the history of what already went out is
     * kept instead of deleted.
     *
     * Existing records are backfilled with the instant they belong to: the
     * event's current checkpoint if they fired at/after it, otherwise
     * their own fired_at — i.e. they were fired against an older schedule
     * and must not suppress the current one.
     */
    public function up(): void
    {
        Schema::table('event_automation_rule_fires', function (Blueprint $table) {
            $table->timestamp('scheduled_for')->nullable()->after('event_automation_rule_id');
        });

        foreach (DB::table('event_automation_rule_fires')->get() as $fire) {
            $event = Event::find($fire->event_id);
            $rule = EventAutomationRule::find($fire->event_automation_rule_id);
            $firedAt = Carbon::parse($fire->fired_at, 'UTC');
            $checkpoint = $event && $rule ? $this->checkpoint($event, $rule) : null;
            $belongsTo = $checkpoint && $firedAt->gte($checkpoint) ? $checkpoint : $firedAt;

            DB::table('event_automation_rule_fires')->where('id', $fire->id)->update(['scheduled_for' => $belongsTo->format('Y-m-d H:i:s')]);
        }

        // Registration-close rules were gated by events.automation_evaluated_at
        // (read raw: the model no longer knows about that column).
        $service = new EventAutomationService;
        $evaluated = DB::table('events')->whereNotNull('automation_evaluated_at')->whereNotNull('inscription_close_at')
            ->get(['id', 'inscription_close_at', 'automation_evaluated_at']);
        foreach ($evaluated as $row) {
            $event = Event::find($row->id);
            $close = Carbon::parse($row->inscription_close_at, 'UTC');
            $evaluatedAt = Carbon::parse($row->automation_evaluated_at, 'UTC');
            foreach ($service->resolveRules($event)->where('trigger', EventAutomationRule::TRIGGER_REGISTRATION_CLOSE) as $rule) {
                DB::table('event_automation_rule_fires')->insert([
                    'event_id' => $event->id,
                    'event_automation_rule_id' => $rule->id,
                    'scheduled_for' => ($evaluatedAt->gte($close) ? $close : $evaluatedAt)->format('Y-m-d H:i:s'),
                    'fired_at' => $evaluatedAt->format('Y-m-d H:i:s'),
                ]);
            }
        }

        // New unique first: MySQL needs an index leading with event_id for
        // that foreign key, which the old one was providing.
        Schema::table('event_automation_rule_fires', function (Blueprint $table) {
            $table->unique(['event_id', 'event_automation_rule_id', 'scheduled_for'], 'event_automation_rule_fires_scheduled_unique');
        });
        Schema::table('event_automation_rule_fires', function (Blueprint $table) {
            $table->dropUnique('event_automation_rule_fires_unique');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('automation_evaluated_at');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('automation_evaluated_at')->nullable()->after('inscriptions_closed');
        });

        // Keep the latest record per (event, rule) so the old unique holds.
        $keep = DB::table('event_automation_rule_fires')
            ->selectRaw('MAX(id) as id')->groupBy('event_id', 'event_automation_rule_id')->pluck('id');
        DB::table('event_automation_rule_fires')->whereNotIn('id', $keep)->delete();

        Schema::table('event_automation_rule_fires', function (Blueprint $table) {
            $table->unique(['event_id', 'event_automation_rule_id'], 'event_automation_rule_fires_unique');
        });
        Schema::table('event_automation_rule_fires', function (Blueprint $table) {
            $table->dropUnique('event_automation_rule_fires_scheduled_unique');
            $table->dropColumn('scheduled_for');
        });
    }

    private function checkpoint(Event $event, EventAutomationRule $rule): ?Carbon
    {
        if ($rule->trigger === EventAutomationRule::TRIGGER_HOURS_BEFORE_EVENT) {
            return $event->startsAt()?->copy()->subHours((int) $rule->hours_before_event)->utc();
        }

        return $event->inscription_close_at?->copy()->utc();
    }
};
