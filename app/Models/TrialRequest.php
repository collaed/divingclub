<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property Carbon|null $preferred_date
 * @property string|null $message
 * @property string|null $status
 * @property string|null $confirmed_by
 * @property Carbon|null $confirmed_date
 * @property string|null $admin_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TrialRequest extends Model
{
    protected $fillable = ['first_name', 'last_name', 'email', 'phone', 'preferred_date', 'message', 'status', 'confirmed_by', 'confirmed_date', 'admin_notes'];

    protected function casts(): array
    {
        return ['preferred_date' => 'date', 'confirmed_date' => 'date'];
    }

    /** @return BelongsTo<User, $this> */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }
}
