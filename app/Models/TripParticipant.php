<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $event_id
 * @property int|null $user_id
 * @property string|null $non_member_name
 * @property int $driving_percentage
 * @property int|null $van_number
 * @property int $local_transit_days
 */
class TripParticipant extends Model
{
    protected $fillable = [
        'event_id',
        'user_id',
        'non_member_name',
        'driving_percentage',
        'van_number',
        'local_transit_days',
        'prepaid_amount',
        'is_supervising_instructor',
        'supervising_days',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function participantName(): string
    {
        if ($this->user) {
            return trim(($this->user->detail?->first_name ?? '').' '.($this->user->detail?->last_name ?? ''));
        }

        return $this->non_member_name ?? __('Unknown');
    }

    public function isNonMember(): bool
    {
        return $this->user_id === null && $this->non_member_name !== null;
    }

    public function isVan(): bool
    {
        $reg = EventRegistration::where('event_id', $this->event_id)
            ->where(function ($q): void {
                if ($this->user_id) {
                    $q->where('user_id', $this->user_id);
                } else {
                    $q->where('non_member_name', $this->non_member_name);
                }
            })
            ->first();

        return $reg?->transit_mode === 'van';
    }
}
