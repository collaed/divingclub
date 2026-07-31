<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $acronym
 * @property string|null $full_name
 * @property string|null $visibility
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Federation extends Model
{
    protected $fillable = ['acronym', 'full_name', 'visibility'];

    public function scopeVisible(Builder $q): Builder
    {
        return $q->whereIn('visibility', ['active', 'recognized']);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('visibility', 'active');
    }

    /** @return HasMany<MemberLicence, $this> */
    public function licences(): HasMany
    {
        return $this->hasMany(MemberLicence::class);
    }

    /** @return HasMany<MedicalComplianceRule, $this> */
    public function complianceRules(): HasMany
    {
        return $this->hasMany(MedicalComplianceRule::class);
    }

    /** @return HasMany<CertificationLevel, $this> */
    public function certificationLevels(): HasMany
    {
        return $this->hasMany(CertificationLevel::class);
    }
}
