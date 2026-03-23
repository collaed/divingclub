<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClubPartnership extends Model
{
    protected $fillable = ['name', 'base_url', 'api_key_id', 'is_active', 'last_sync_at'];

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

    public function externalRegistrations()
    {
        return $this->hasMany(ExternalRegistration::class, 'partnership_id');
    }
}
