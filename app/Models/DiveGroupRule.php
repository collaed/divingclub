<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DiveGroupRule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive($q) { return $q->where('is_active', true); }

    public function matchesDiver(?int $diverRank): bool
    {
        if ($this->diver_condition === 'no_cert') return $diverRank === null || $diverRank === 0;
        if ($this->diver_condition === 'any') return true;
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
        if (!$leaderRank) return false;
        if ($this->leader_category === 'instructor' && $leaderCategory !== 'instructor') return false;
        return $leaderRank >= $this->min_leader_rank;
    }

    public const DIVE_MODES = ['supervised', 'autonomous', 'training', 'certification'];
}
