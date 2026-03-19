<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialPublishLog extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function publishable() { return $this->morphTo(); }
}
