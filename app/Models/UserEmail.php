<?php

namespace App\Models;

use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $email
 * @property bool $is_primary
 * @property bool $is_verified
 * @property string|null $receive_mail
 * @property string|null $label
 * @property string|null $verification_token
 * @property Carbon|null $verification_sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserEmail extends Model
{
    use Auditable;

    protected $fillable = ['user_id', 'email', 'is_primary', 'is_verified', 'receive_mail', 'label', 'verification_token', 'verification_sent_at'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'is_verified' => 'boolean', 'verification_sent_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
