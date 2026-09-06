<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named eligibility set (base category) grouping the member statuses a member
 * of that category may hold across seasons. The set is the sticky base
 * category (e.g. Fonctionnaire vs Externe); the member's current-year status
 * is stored separately on users.status_id. Two members currently on
 * "sympathisant" can belong to different sets and therefore be offered
 * different statuses when they return to full membership.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StatusSet extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    /**
     * Statuses offered by this set. The pivot carries `is_default` to mark the
     * "full membership" status of the set.
     *
     * @return BelongsToMany<MemberStatus, $this>
     */
    public function statuses(): BelongsToMany
    {
        return $this->belongsToMany(MemberStatus::class, 'status_set_members', 'status_set_id', 'member_status_id')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    /** The default (full membership) status of this set, if configured. */
    public function defaultStatus(): ?MemberStatus
    {
        return $this->statuses->firstWhere('pivot.is_default', true);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'status_set_id');
    }
}
