<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserEmail extends Model
{
    use \App\Traits\Auditable;
    protected $fillable = ['user_id', 'email', 'is_primary', 'is_verified', 'label', 'verification_token', 'verification_sent_at'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'is_verified' => 'boolean', 'verification_sent_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
