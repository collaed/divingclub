<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeasonHoliday extends Model
{
    protected $fillable = ['season_id', 'name', 'start_date', 'end_date', 'is_adhoc'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'is_adhoc' => 'boolean'];
    }

    public function season()
    {
        return $this->belongsTo(Season::class);
    }
}
