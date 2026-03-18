<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiveGroupMember extends Model
{
    protected $guarded = ['id'];

    public function group() { return $this->belongsTo(DiveGroup::class, 'dive_group_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
