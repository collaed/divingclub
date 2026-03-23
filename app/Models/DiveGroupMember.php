<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiveGroupMember extends Model
{
    protected $fillable = ['dive_group_id', 'user_id', 'role'];

    public function group()
    {
        return $this->belongsTo(DiveGroup::class, 'dive_group_id');
    }

    public function diveGroup()
    {
        return $this->group();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
