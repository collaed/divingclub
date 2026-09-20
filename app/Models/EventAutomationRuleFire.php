<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records that one EventAutomationRule has fired (or been decided as no
 * longer applicable) for one Event at one due instant — the idempotency
 * gate for every automation trigger. Keyed by scheduled_for (the instant
 * the rule was due: the close time, or start minus hours_before_event), so
 * a rescheduled event no longer matches its old record and the rule can
 * fire again, while the history of what already went out is kept. See
 * EventAutomationService::alreadyFired().
 *
 * @property int $id
 * @property int $event_id
 * @property int $event_automation_rule_id
 * @property Carbon $scheduled_for
 * @property Carbon $fired_at
 */
class EventAutomationRuleFire extends Model
{
    public $timestamps = false;

    protected $fillable = ['event_id', 'event_automation_rule_id', 'scheduled_for', 'fired_at'];

    protected function casts(): array
    {
        return ['scheduled_for' => 'datetime', 'fired_at' => 'datetime'];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<EventAutomationRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(EventAutomationRule::class, 'event_automation_rule_id');
    }
}
