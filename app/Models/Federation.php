<?php

namespace App\Models;

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

    public function scopeVisible($q)
    {
        return $q->whereIn('visibility', ['active', 'recognized']);
    }

    public function scopeActive($q)
    {
        return $q->where('visibility', 'active');
    }

    public function licences(): HasMany
    {
        return $this->hasMany(MemberLicence::class);
    }

    public function complianceRules(): HasMany
    {
        return $this->hasMany(MedicalComplianceRule::class);
    }

    public function certificationLevels(): HasMany
    {
        return $this->hasMany(CertificationLevel::class);
    }
}
