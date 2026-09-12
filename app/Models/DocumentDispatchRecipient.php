<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $dispatch_id
 * @property int|null $user_id
 * @property string $email
 * @property string $token
 * @property Carbon|null $sent_at
 * @property string|null $send_error
 * @property Carbon|null $first_opened_at
 * @property int $opens_count
 */
class DocumentDispatchRecipient extends Model
{
    protected $fillable = ['dispatch_id', 'user_id', 'email', 'token', 'sent_at', 'send_error', 'first_opened_at', 'opens_count'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'first_opened_at' => 'datetime', 'opens_count' => 'integer'];
    }

    /** @return BelongsTo<DocumentDispatch, $this> */
    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(DocumentDispatch::class, 'dispatch_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<DocumentDispatchOpen, $this> */
    public function opens(): HasMany
    {
        return $this->hasMany(DocumentDispatchOpen::class, 'recipient_id')->latest('opened_at');
    }
}
