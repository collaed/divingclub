<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentLoan extends Model
{
    protected $fillable = [
        'equipment_id', 'user_id', 'event_id', 'loan_reason',
        'loaned_at', 'expected_return_date', 'returned_at',
        'loaned_by', 'returned_by', 'reminder_sent_at',
        'loan_email_sent_at', 'return_email_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'loaned_at' => 'date',
            'returned_at' => 'date',
            'expected_return_date' => 'date',
            'reminder_sent_at' => 'date',
            'loan_email_sent_at' => 'datetime',
            'return_email_sent_at' => 'datetime',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
