<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int|null $federation_id
 * @property string|null $code
 * @property string|null $name
 * @property string|null $category
 * @property string|null $rank
 * @property string|null $equivalence_group
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CertificationLevel extends Model
{
    protected $fillable = ['federation_id', 'code', 'name', 'category', 'rank', 'equivalence_group'];

    /** @return BelongsTo<Federation, $this> */
    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_certification_levels')->withPivot('obtained_date', 'is_primary', 'display_priority')->withTimestamps();
    }

    // Get equivalent certs across federations
    public function equivalents(): BelongsToMany
    {
        if (! $this->equivalence_group) {
            return collect();
        }

        return static::where('equivalence_group', $this->equivalence_group)->where('id', '!=', $this->id)->with('federation')->get();
    }

    public function label(): string
    {
        return $this->code.' ('.$this->federation?->acronym.')';
    }
}
