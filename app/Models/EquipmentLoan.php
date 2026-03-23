<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentLoan extends Model
{
    protected $fillable = ['equipment_id', 'user_id', 'loaned_at', 'expected_return_date', 'returned_at', 'loaned_by', 'returned_by', 'reminder_sent_at'];

    protected function casts(): array
    {
        return ['loaned_at' => 'date', 'returned_at' => 'date', 'expected_return_date' => 'date', 'reminder_sent_at' => 'date'];
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
