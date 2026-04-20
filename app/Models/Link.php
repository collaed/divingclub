<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $title
 * @property string|null $url
 * @property string|null $image_url
 * @property string|null $description
 * @property bool $is_public
 * @property string|null $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Link extends Model
{
    protected $fillable = ['title', 'url', 'image_url', 'description', 'is_public', 'sort_order'];

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }
}
