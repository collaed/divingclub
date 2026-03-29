<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiveGroupMember extends Model
{
    protected $fillable = ['dive_group_id', 'user_id', 'role'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(DiveGroup::class, 'dive_group_id');
    }

    public function diveGroup()
    {
        return $this->group();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
