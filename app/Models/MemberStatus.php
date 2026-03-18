<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberStatus extends Model
{
    protected $fillable = ['name', 'slug', 'fee_multiplier', 'description'];

    protected function casts(): array
    {
        return ['fee_multiplier' => 'decimal:2'];
    }

    public function users()
    {
        return $this->hasMany(User::class, 'status_id');
    }
}
