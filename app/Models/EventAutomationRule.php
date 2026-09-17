<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An automated action evaluated once an event's registrations close.
 * Attached to a season_pattern (the default for every event it generates)
 * or to one specific event (overrides the pattern's rule of the same
 * rule_type for that event only — see EventAutomationService::resolveRules).
 *
 * @property int $id
 * @property int|null $season_pattern_id
 * @property int|null $event_id
 * @property string $rule_type
 * @property string $trigger
 * @property int|null $hours_before_event
 * @property int|null $threshold
 * @property bool $cancels_event
 * @property string|null $email_subject
 * @property string|null $email_body
 * @property string|null $extra_recipients
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EventAutomationRule extends Model
{
    public const TYPE_MIN_REGISTRATIONS = 'min_registrations';

    public const TYPE_REQUIRES_LIFEGUARD = 'requires_lifeguard';

    /** Evaluated when the event's registration window closes (default). */
    public const TRIGGER_REGISTRATION_CLOSE = 'registration_close';

    /** Evaluated a fixed number of hours before the event starts, regardless of the registration window. */
    public const TRIGGER_HOURS_BEFORE_EVENT = 'hours_before_event';

    protected $fillable = [
        'season_pattern_id', 'event_id', 'rule_type', 'trigger', 'hours_before_event',
        'threshold', 'cancels_event', 'email_subject', 'email_body', 'extra_recipients',
    ];

    protected function casts(): array
    {
        return ['cancels_event' => 'boolean'];
    }

    /** @return array<int, string> Fixed extra recipient addresses, trimmed, blanks dropped. */
    public function extraRecipientList(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->extra_recipients))));
    }

    /** @return BelongsTo<SeasonPattern, $this> */
    public function seasonPattern(): BelongsTo
    {
        return $this->belongsTo(SeasonPattern::class);
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
