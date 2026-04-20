<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $endpoint
 * @property string|null $p256dh
 * @property string|null $auth
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PushSubscription extends Model
{
    protected $fillable = ['user_id', 'endpoint', 'p256dh', 'auth'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
