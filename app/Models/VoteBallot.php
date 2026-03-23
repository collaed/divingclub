<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoteBallot extends Model
{
    protected $fillable = ['vote_id', 'vote_option_id', 'token_hash'];

    public function vote()
    {
        return $this->belongsTo(Vote::class);
    }

    public function option()
    {
        return $this->belongsTo(VoteOption::class, 'vote_option_id');
    }
}
