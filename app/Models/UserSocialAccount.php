<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSocialAccount extends Model
{
    protected $fillable = ['user_id', 'provider', 'provider_user_id', 'email', 'token', 'refresh_token'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'refresh_token' => 'encrypted'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
