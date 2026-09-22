<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $kind
 * @property string $status
 * @property int|null $event_id
 * @property string|null $budget_amount
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class LedgerOperation extends Model
{
    public const KIND_LOOP = 'loop';

    public const KIND_TRIP = 'trip';

    public const KIND_COST_CENTRE = 'cost_centre';

    public const KIND_OTHER = 'other';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = ['name', 'kind', 'status', 'event_id', 'budget_amount', 'notes'];

    /** @return HasMany<LedgerTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(LedgerTransaction::class, 'operation_id');
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** Sum of every linked transaction's amount — zero for a balanced loop. */
    public function net(): float
    {
        return (float) $this->transactions()->sum('amount');
    }
}
