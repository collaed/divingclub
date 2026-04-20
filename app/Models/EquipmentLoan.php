<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $equipment_id
 * @property int|null $user_id
 * @property Carbon|null $loaned_at
 * @property Carbon|null $expected_return_date
 * @property Carbon|null $returned_at
 * @property string|null $loaned_by
 * @property string|null $returned_by
 * @property Carbon|null $reminder_sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Equipment $equipment
 * @property-read User $user
 * @property-read Event|null $event
 */
class EquipmentLoan extends Model
{
    protected $fillable = ['equipment_id', 'user_id', 'loaned_at', 'expected_return_date', 'returned_at', 'loaned_by', 'returned_by', 'reminder_sent_at'];

    protected function casts(): array
    {
        return ['loaned_at' => 'date', 'returned_at' => 'date', 'expected_return_date' => 'date', 'reminder_sent_at' => 'date'];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
