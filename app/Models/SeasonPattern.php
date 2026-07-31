<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $season_id
 * @property string|null $day_of_week
 * @property string|null $start_time
 * @property string|null $end_time
 * @property string|null $event_type
 * @property string|null $title
 * @property string|null $location
 * @property string|null $description
 * @property string|null $max_participants
 * @property string|null $estimated_cost
 * @property string|null $registration_opens_days_before
 * @property string|null $registration_closes_days_before
 * @property string|null $color_hex
 * @property string|null $whatsapp_group_url
 * @property int|null $dive_site_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SeasonPattern extends Model
{
    protected $fillable = [
        'season_id', 'day_of_week', 'start_time', 'end_time', 'event_type',
        'title', 'location', 'description', 'max_participants', 'estimated_cost',
        'registration_opens_days_before', 'registration_closes_days_before',
        'color_hex', 'whatsapp_group_url', 'dive_site_id',
    ];

    /** @return BelongsTo<Season, $this> */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function dayName(): string
    {
        return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][$this->day_of_week] ?? '?';
    }
}
