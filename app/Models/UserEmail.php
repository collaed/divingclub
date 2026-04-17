<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEmail extends Model
{
    use Auditable;

    protected $fillable = ['user_id', 'email', 'is_primary', 'is_verified', 'receive_mail', 'label', 'verification_token', 'verification_sent_at'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'is_verified' => 'boolean', 'verification_sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
