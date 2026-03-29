<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
