<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $event_id
 * @property int|null $event_registration_id
 * @property int $quantity
 * @property int $quantity_returned
 * @property int|null $loaned_by
 * @property Carbon $loaned_at
 * @property Carbon|null $returned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read EventRegistration|null $eventRegistration
 */
class EventWeightLoan extends Model
{
    protected $fillable = ['event_id', 'event_registration_id', 'quantity', 'quantity_returned', 'loaned_by', 'loaned_at', 'returned_at'];

    protected function casts(): array
    {
        return ['loaned_at' => 'datetime', 'returned_at' => 'datetime'];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<EventRegistration, $this> */
    public function eventRegistration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class);
    }

    public function outstanding(): int
    {
        return $this->quantity - $this->quantity_returned;
    }
}
