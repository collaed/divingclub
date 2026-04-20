<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $scope
 * @property string|null $diver_condition
 * @property string|null $dive_mode
 * @property string|null $min_leader_rank
 * @property string|null $leader_category
 * @property string|null $max_depth
 * @property string|null $max_group_size
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DiveGroupRule extends Model
{
    protected $fillable = ['name', 'scope', 'diver_condition', 'dive_mode', 'min_leader_rank', 'leader_category', 'max_depth', 'max_group_size', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function matchesDiver(?int $diverRank): bool
    {
        if ($this->diver_condition === 'no_cert') {
            return $diverRank === null || $diverRank === 0;
        }
        if ($this->diver_condition === 'any') {
            return true;
        }
        if (Str::startsWith($this->diver_condition, 'max_rank:')) {
            return ($diverRank ?? 0) <= (int) Str::after($this->diver_condition, 'max_rank:');
        }
        if (Str::startsWith($this->diver_condition, 'min_rank:')) {
            return ($diverRank ?? 0) >= (int) Str::after($this->diver_condition, 'min_rank:');
        }

        return false;
    }

    public function leaderSatisfied(?int $leaderRank, ?string $leaderCategory): bool
    {
        if (! $leaderRank) {
            return false;
        }
        if ($this->leader_category === 'instructor' && $leaderCategory !== 'instructor') {
            return false;
        }

        return $leaderRank >= $this->min_leader_rank;
    }

    public const DIVE_MODES = ['supervised', 'autonomous', 'training', 'certification'];
}
