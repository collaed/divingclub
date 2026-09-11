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

    /**
     * The fee row that applies to a status for a season: its own row when one
     * is defined, otherwise the row for the status it's a fee-alias of (see
     * `MemberStatus::FEE_ALIASES`) — e.g. "Famille"/"Associé"/"Assimilé" pay
     * the same cotisation as "Membre de droit" unless the bureau sets a fee
     * of their own.
     */
    public static function resolveForStatus(?MemberStatus $status, string $seasonYear): ?self
    {
        if (! $status instanceof MemberStatus) {
            return null;
        }

        $fee = static::where('season_year', $seasonYear)->where('status_id', $status->id)->first();
        if ($fee) {
            return $fee;
        }

        $aliasSlug = $status->feeAliasSlug();
        if (! $aliasSlug) {
            return null;
        }

        $aliasStatus = MemberStatus::where('slug', $aliasSlug)->first();

        return $aliasStatus
            ? static::where('season_year', $seasonYear)->where('status_id', $aliasStatus->id)->first()
            : null;
    }
}
