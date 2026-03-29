<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberStatus extends Model
{
    protected $fillable = ['name', 'slug', 'fee_multiplier', 'description'];

    protected function casts(): array
    {
        return ['fee_multiplier' => 'decimal:2'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'status_id');
    }
}
