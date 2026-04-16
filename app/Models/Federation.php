<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
