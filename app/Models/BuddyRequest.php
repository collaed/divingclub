<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuddyRequest extends Model
{
    protected $fillable = ['user_id', 'dive_site_id', 'location_text', 'dive_date', 'dive_time', 'need_type', 'description', 'max_depth', 'desired_cert_level', 'max_buddies', 'is_active'];

    protected function casts(): array
    {
        return ['dive_date' => 'date', 'is_active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function diveSite(): BelongsTo
    {
        return $this->belongsTo(DiveSite::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(BuddyResponse::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->where('dive_date', '>=', today());
    }

    public function locationLabel(): string
    {
        return $this->diveSite?->name ?? $this->location_text ?? '—';
    }

    public const NEED_TYPES = [
        'buddy' => '🤝 Buddy',
        'guide' => '👑 Guide de Palanquée / Divemaster',
        'dp' => '📋 Directeur de Plongée',
    ];
}
