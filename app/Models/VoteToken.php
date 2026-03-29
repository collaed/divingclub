<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
