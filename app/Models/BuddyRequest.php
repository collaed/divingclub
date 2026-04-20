<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $dive_site_id
 * @property string|null $location_text
 * @property Carbon|null $dive_date
 * @property string|null $dive_time
 * @property string|null $need_type
 * @property string|null $description
 * @property string|null $max_depth
 * @property string|null $desired_cert_level
 * @property string|null $max_buddies
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BuddyRequest extends Model
{
    protected $fillable = ['user_id', 'dive_site_id', 'location_text', 'dive_date', 'dive_time', 'need_type', 'description', 'max_depth', 'desired_cert_level', 'max_buddies', 'is_active'];

    protected function casts(): array
    {
        return ['dive_date' => 'date', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<DiveSite, $this> */
    public function diveSite(): BelongsTo
    {
        return $this->belongsTo(DiveSite::class);
    }

    /** @return HasMany<BuddyResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(BuddyResponse::class);
    }

    public function scopeActive(Builder $q): Builder
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
