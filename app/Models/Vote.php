<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vote extends Model
{
    use SoftDeletes;

    protected $fillable = ['title', 'description', 'mode', 'allow_multiple', 'allow_change', 'num_positions', 'min_vote_pct', 'is_public', 'status', 'opens_at', 'closes_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'allow_multiple' => 'boolean',
            'allow_change' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(VoteOption::class);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(VoteToken::class);
    }

    public function ballots(): HasMany
    {
        return $this->hasMany(VoteBallot::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open'
            && (! $this->opens_at || $this->opens_at->isPast())
            && (! $this->closes_at || $this->closes_at->isFuture());
    }
}
