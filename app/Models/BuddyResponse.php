<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $buddy_request_id
 * @property int|null $user_id
 * @property string|null $message
 * @property string|null $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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
