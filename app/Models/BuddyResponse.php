<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuddyResponse extends Model
{
    protected $fillable = ['buddy_request_id', 'user_id', 'message', 'status'];

    public function buddyRequest()
    {
        return $this->belongsTo(BuddyRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
