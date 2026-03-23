<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipFee extends Model
{
    protected $fillable = ['season_year', 'status_id', 'amount', 'label', 'notes'];

    public function status()
    {
        return $this->belongsTo(MemberStatus::class, 'status_id');
    }
}
