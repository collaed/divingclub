<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrialRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['preferred_date' => 'date', 'confirmed_date' => 'date'];
    }

    public function confirmedBy() { return $this->belongsTo(User::class, 'confirmed_by'); }

    public function scopePending($q) { return $q->where('status', 'pending'); }
}
