<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Season extends Model
{
    protected $fillable = ['year', 'name', 'start_date', 'end_date', 'is_active'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'is_active' => 'boolean'];
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(SeasonHoliday::class);
    }

    public function patterns(): HasMany
    {
        return $this->hasMany(SeasonPattern::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
