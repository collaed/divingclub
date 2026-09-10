<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $ip_address
 * @property string|null $country_code
 * @property string|null $country_name
 * @property string|null $user_agent
 * @property string|null $guard
 * @property bool $remember
 * @property Carbon|null $created_at
 * @property-read User|null $user
 */
class LoginRecord extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'ip_address', 'country_code', 'country_name', 'user_agent', 'guard', 'remember'];

    protected function casts(): array
    {
        return [
            'remember' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
