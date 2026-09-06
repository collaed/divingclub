<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $season_id
 * @property string|null $name
 * @property string|null $slug
 * @property string $kind
 * @property string|null $amount
 * @property bool $is_base
 * @property bool $is_optional
 * @property bool $prorata_eligible
 * @property int|null $taper_below_age
 * @property string|null $taper_ratio
 * @property Carbon|null $age_anchor_date
 * @property string|null $description
 * @property string|null $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MembershipFeeComponent extends Model
{
    public const KIND_FFESSM_LICENCE = 'ffessm_licence';

    public const KIND_FLASSA = 'flassa';

    public const KIND_ASSURANCE = 'assurance';

    public const KIND_OTHER = 'other';

    protected $fillable = ['season_id', 'name', 'slug', 'kind', 'amount', 'is_base', 'is_optional', 'prorata_eligible', 'taper_below_age', 'taper_ratio', 'age_anchor_date', 'description', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_base' => 'boolean',
            'is_optional' => 'boolean',
            'prorata_eligible' => 'boolean',
            'taper_below_age' => 'integer',
            'taper_ratio' => 'decimal:3',
            'age_anchor_date' => 'date',
        ];
    }

    /** @return BelongsTo<Season, $this> */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}
