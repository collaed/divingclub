<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipFeeComponent extends Model
{
    protected $fillable = ['season_id', 'name', 'slug', 'amount', 'is_base', 'is_optional', 'description', 'sort_order'];

    protected function casts(): array
    {
        return ['is_base' => 'boolean', 'is_optional' => 'boolean'];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}
