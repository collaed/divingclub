<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $vote_id
 * @property string|null $label
 * @property string|null $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class VoteOption extends Model
{
    protected $fillable = ['vote_id', 'label', 'sort_order'];

    public function vote(): BelongsTo
    {
        return $this->belongsTo(Vote::class);
    }

    public function ballots(): HasMany
    {
        return $this->hasMany(VoteBallot::class);
    }
}
