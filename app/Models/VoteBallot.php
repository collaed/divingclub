<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $vote_id
 * @property int|null $vote_option_id
 * @property string|null $token_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class VoteBallot extends Model
{
    protected $fillable = ['vote_id', 'vote_option_id', 'token_hash'];

    public function vote(): BelongsTo
    {
        return $this->belongsTo(Vote::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(VoteOption::class, 'vote_option_id');
    }
}
