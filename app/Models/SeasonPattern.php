<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeasonPattern extends Model
{
    protected $fillable = [
        'season_id', 'day_of_week', 'start_time', 'end_time', 'event_type',
        'title', 'location', 'description', 'max_participants', 'estimated_cost',
        'registration_opens_days_before', 'registration_closes_days_before',
        'color_hex', 'whatsapp_group_url', 'dive_site_id',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function dayName(): string
    {
        return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][$this->day_of_week] ?? '?';
    }
}
