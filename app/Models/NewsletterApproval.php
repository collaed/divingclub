<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterApproval extends Model
{
    protected $fillable = ['newsletter_id', 'user_id', 'approved', 'comment'];

    protected function casts(): array
    {
        return ['approved' => 'boolean'];
    }

    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Newsletter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
