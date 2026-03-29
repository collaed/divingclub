<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiveGroup extends Model
{
    protected $fillable = ['event_id', 'name', 'dive_mode', 'purpose', 'planned_depth', 'planned_duration', 'gas_mix', 'line_number', 'planned_entry_time', 'planned_exit_time', 'notes', 'created_by'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(DiveGroupMember::class);
    }

    public function leader()
    {
        return $this->members()->where('role', 'leader')->first();
    }

    public function divers()
    {
        return $this->members()->where('role', 'diver');
    }

    public const DIVE_MODES = ['supervised', 'autonomous', 'training', 'certification'];

    public const GAS_MIXES = [
        'air' => 'Air',
        'nitrox32' => 'Nitrox 32%',
        'nitrox36' => 'Nitrox 36%',
        'nitrox40' => 'Nitrox 40%',
        'trimix' => 'Trimix',
        'O2' => 'O₂ (deco)',
    ];
}
