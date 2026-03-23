<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoteToken extends Model
{
    protected $fillable = ['vote_id', 'user_id', 'token', 'is_consumed', 'consumed_at'];

    protected function casts(): array
    {
        return ['is_consumed' => 'boolean', 'consumed_at' => 'datetime'];
    }

    public function vote()
    {
        return $this->belongsTo(Vote::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
