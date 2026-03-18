<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Federation extends Model
{
    protected $fillable = ['acronym', 'full_name'];

    public function licences()
    {
        return $this->hasMany(MemberLicence::class);
    }

    public function certificationLevels()
    {
        return $this->hasMany(CertificationLevel::class);
    }
}
