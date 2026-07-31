<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
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

    /** @return BelongsTo<BuddyRequest, $this> */
    public function buddyRequest(): BelongsTo
    {
        return $this->belongsTo(BuddyRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
