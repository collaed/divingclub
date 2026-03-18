<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipFee extends Model
{
    protected $guarded = ['id'];

    public function status() { return $this->belongsTo(MemberStatus::class, 'status_id'); }
}
