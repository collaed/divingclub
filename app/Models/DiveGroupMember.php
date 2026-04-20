<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $dive_group_id
 * @property int|null $user_id
 * @property string|null $role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read DiveGroup $group
 */
class DiveGroupMember extends Model
{
    protected $fillable = ['dive_group_id', 'user_id', 'role'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(DiveGroup::class, 'dive_group_id');
    }

    public function diveGroup()
    {
        return $this->group();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
