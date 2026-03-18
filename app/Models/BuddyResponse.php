<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuddyResponse extends Model
{
    protected $guarded = ['id'];

    public function buddyRequest() { return $this->belongsTo(BuddyRequest::class); }
    public function user() { return $this->belongsTo(User::class); }
}
