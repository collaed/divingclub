<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $year
 * @property string|null $name
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Season extends Model
{
    protected $fillable = ['year', 'name', 'start_date', 'end_date', 'is_active'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'is_active' => 'boolean'];
    }

    /** @return HasMany<SeasonHoliday, $this> */
    public function holidays(): HasMany
    {
        return $this->hasMany(SeasonHoliday::class);
    }

    /** @return HasMany<SeasonPattern, $this> */
    public function patterns(): HasMany
    {
        return $this->hasMany(SeasonPattern::class);
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
