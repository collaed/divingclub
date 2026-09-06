<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $slug
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MemberStatus extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    /**
     * Slugs considered "inactive": lapsed/former/past members. These are the
     * single source of truth for excluding members from club-wide mail and
     * from default member listings (they remain visible only in the bureau
     * historic view). `honoraire` is intentionally NOT inactive: honorary
     * members still receive mail and can register for events.
     *
     * @var array<int, string>
     */
    public const INACTIVE_SLUGS = ['former'];

    /**
     * All slugs that count as "active members" for mailing and listings.
     * Any status that is not explicitly inactive is active.
     *
     * @return array<int, string>
     */
    public static function activeSlugs(): array
    {
        return static::query()
            ->whereNotIn('slug', self::INACTIVE_SLUGS)
            ->pluck('slug')
            ->all();
    }

    /** @return array<int, string> */
    public static function inactiveSlugs(): array
    {
        return self::INACTIVE_SLUGS;
    }

    /**
     * IDs of statuses considered inactive. Cached-friendly small query used by
     * listings that filter on status_id.
     *
     * @return Collection<int, int>
     */
    public static function inactiveIds(): Collection
    {
        return static::query()->whereIn('slug', self::INACTIVE_SLUGS)->pluck('id');
    }

    public function isActive(): bool
    {
        return ! in_array($this->slug, self::INACTIVE_SLUGS, true);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'status_id');
    }

    /** @return BelongsToMany<StatusSet, $this> */
    public function statusSets(): BelongsToMany
    {
        return $this->belongsToMany(StatusSet::class, 'status_set_members', 'member_status_id', 'status_set_id')
            ->withTimestamps();
    }
}
