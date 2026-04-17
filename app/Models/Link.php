<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Link extends Model
{
    protected $fillable = ['title', 'url', 'image_url', 'description', 'is_public', 'sort_order'];

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }
}
