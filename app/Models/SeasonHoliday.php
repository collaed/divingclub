<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $season_id
 * @property string|null $name
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property bool $is_adhoc
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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
