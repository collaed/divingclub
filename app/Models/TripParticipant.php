<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $event_id
 * @property int $user_id
 * @property int $driving_percentage
 * @property int $local_transit_days
 */
class TripParticipant extends Model
{
    protected $fillable = [
        'event_id',
        'user_id',
        'driving_percentage',
        'local_transit_days',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVan(): bool
    {
        $reg = EventRegistration::where('event_id', $this->event_id)
            ->where('user_id', $this->user_id)
            ->first();

        return $reg?->transit_mode === 'van';
    }
}
