<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $newsletter_id
 * @property int|null $user_id
 * @property bool $approved
 * @property string|null $comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class NewsletterApproval extends Model
{
    protected $fillable = ['newsletter_id', 'user_id', 'approved', 'comment'];

    protected function casts(): array
    {
        return ['approved' => 'boolean'];
    }

    /** @return BelongsTo<Newsletter, $this> */
    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Newsletter::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
