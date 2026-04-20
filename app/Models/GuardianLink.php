<?php

namespace App\Models;

use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $guardian_user_id
 * @property int|null $minor_user_id
 * @property string|null $relationship
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class GuardianLink extends Model
{
    use Auditable;

    protected $fillable = ['guardian_user_id', 'minor_user_id', 'relationship'];

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }

    public function minor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'minor_user_id');
    }
}
