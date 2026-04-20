<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $provider
 * @property int|null $provider_user_id
 * @property string|null $email
 * @property string|null $token
 * @property string|null $refresh_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserSocialAccount extends Model
{
    protected $fillable = ['user_id', 'provider', 'provider_user_id', 'email', 'token', 'refresh_token'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'refresh_token' => 'encrypted'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
