<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $vote_id
 * @property int|null $user_id
 * @property string|null $token
 * @property bool $is_consumed
 * @property Carbon|null $consumed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class VoteToken extends Model
{
    protected $fillable = ['vote_id', 'user_id', 'token', 'is_consumed', 'consumed_at'];

    protected function casts(): array
    {
        return ['is_consumed' => 'boolean', 'consumed_at' => 'datetime'];
    }

    public function vote(): BelongsTo
    {
        return $this->belongsTo(Vote::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
