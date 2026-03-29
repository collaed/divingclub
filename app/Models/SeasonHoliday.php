<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeasonHoliday extends Model
{
    protected $fillable = ['season_id', 'name', 'start_date', 'end_date', 'is_adhoc'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'is_adhoc' => 'boolean'];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}
