<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GdprConsent extends Model
{
    protected $fillable = ['user_id', 'consent_type', 'granted', 'granted_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['granted' => 'boolean', 'granted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
