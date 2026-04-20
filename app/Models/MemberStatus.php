<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $slug
 * @property float $fee_multiplier
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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
