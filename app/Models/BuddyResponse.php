<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuddyResponse extends Model
{
    protected $fillable = ['buddy_request_id', 'user_id', 'message', 'status'];

    public function buddyRequest(): BelongsTo
    {
        return $this->belongsTo(BuddyRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
