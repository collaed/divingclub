<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $event_id
 * @property string|null $name
 * @property string|null $dive_mode
 * @property string|null $purpose
 * @property string|null $planned_depth
 * @property string|null $planned_duration
 * @property string|null $gas_mix
 * @property string|null $line_number
 * @property string|null $planned_entry_time
 * @property string|null $planned_exit_time
 * @property string|null $notes
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection $members
 * @property-read Event $event
 */
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
