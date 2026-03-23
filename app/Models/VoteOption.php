<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoteOption extends Model
{
    protected $fillable = ['vote_id', 'label', 'sort_order'];

    public function vote()
    {
        return $this->belongsTo(Vote::class);
    }

    public function ballots()
    {
        return $this->hasMany(VoteBallot::class);
    }
}
