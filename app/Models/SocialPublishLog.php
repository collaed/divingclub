<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string|null $platform
 * @property string|null $publishable_type
 * @property int|null $publishable_id
 * @property int|null $external_post_id
 * @property string|null $status
 * @property string|null $error_message
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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
