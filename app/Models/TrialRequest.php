<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrialRequest extends Model
{
    protected $fillable = ['first_name', 'last_name', 'email', 'phone', 'preferred_date', 'message', 'status', 'confirmed_by', 'confirmed_date', 'admin_notes'];

    protected function casts(): array
    {
        return ['preferred_date' => 'date', 'confirmed_date' => 'date'];
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }
}
