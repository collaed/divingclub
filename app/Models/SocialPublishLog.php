<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SocialPublishLog extends Model
{
    protected $fillable = ['platform', 'publishable_type', 'publishable_id', 'external_post_id', 'status', 'error_message', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function publishable(): MorphTo
    {
        return $this->morphTo();
    }
}
