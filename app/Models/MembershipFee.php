<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $season_id
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
    protected $fillable = ['season_id', 'season_year', 'status_id', 'amount', 'label', 'notes'];

    /** @return BelongsTo<MemberStatus, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(MemberStatus::class, 'status_id');
    }

    /** @return BelongsTo<Season, $this> */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class, 'season_id');
    }
}
