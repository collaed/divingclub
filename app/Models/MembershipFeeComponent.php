<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipFeeComponent extends Model
{
    protected $fillable = ['season_id', 'name', 'slug', 'amount', 'is_base', 'is_optional', 'description', 'sort_order'];

    protected function casts(): array
    {
        return ['is_base' => 'boolean', 'is_optional' => 'boolean'];
    }

    public function season()
    {
        return $this->belongsTo(Season::class);
    }
}
