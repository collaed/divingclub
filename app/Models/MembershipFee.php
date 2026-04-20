<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string|null $season_year
 * @property int|null $status_id
 * @property string|null $amount
 * @property string|null $label
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MembershipFee extends Model
{
    protected $fillable = ['season_year', 'status_id', 'amount', 'label', 'notes'];

    public function status(): BelongsTo
    {
        return $this->belongsTo(MemberStatus::class, 'status_id');
    }
}
