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
 * @property string|null $amount
 * @property bool $is_base
 * @property bool $is_optional
 * @property string|null $description
 * @property string|null $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MembershipFeeComponent extends Model
{
    protected $fillable = ['season_id', 'name', 'slug', 'amount', 'is_base', 'is_optional', 'description', 'sort_order'];

    protected function casts(): array
    {
        return ['is_base' => 'boolean', 'is_optional' => 'boolean'];
    }

    /** @return BelongsTo<Season, $this> */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}
