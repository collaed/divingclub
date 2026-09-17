<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records that one EventAutomationRule has already fired (or been decided
 * as no longer applicable) for one Event — the per-rule idempotency gate
 * for hours_before_event rules, since a single event can have several such
 * rules with different offsets that must each fire independently. See
 * EventAutomationService::evaluateHoursBeforeEvent().
 *
 * @property int $id
 * @property int $event_id
 * @property int $event_automation_rule_id
 * @property Carbon $fired_at
 */
class EventAutomationRuleFire extends Model
{
    public $timestamps = false;

    protected $fillable = ['event_id', 'event_automation_rule_id', 'fired_at'];

    protected function casts(): array
    {
        return ['fired_at' => 'datetime'];
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
