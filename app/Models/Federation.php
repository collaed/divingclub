<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function licences()
    {
        return $this->hasMany(MemberLicence::class);
    }

    public function certificationLevels()
    {
        return $this->hasMany(CertificationLevel::class);
    }
}
