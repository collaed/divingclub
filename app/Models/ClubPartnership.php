<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $base_url
 * @property int|null $api_key_id
 * @property string|null $api_secret_hash
 * @property int|null $their_api_key_id
 * @property string|null $their_api_secret
 * @property bool $is_active
 * @property Carbon|null $last_sync_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ClubPartnership extends Model
{
    protected $fillable = ['name', 'base_url', 'api_key_id', 'api_secret_hash', 'their_api_key_id', 'their_api_secret', 'is_active', 'last_sync_at'];

    protected $casts = [
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    public static function generateKeyPair(): array
    {
        return [
            'key_id' => 'dc_'.Str::random(32),
            'secret' => Str::random(64),
        ];
    }

    /** @return HasMany<ExternalRegistration, $this> */
    public function externalRegistrations(): HasMany
    {
        return $this->hasMany(ExternalRegistration::class, 'partnership_id');
    }
}
