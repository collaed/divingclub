<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class MemberDetail extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'brevet_date' => 'date',
            'last_dive_date' => 'date',
            'other_certifications' => 'array',
            'training_enrollments' => 'array',
            'cotisation_years' => 'array',
            'bureau_member' => 'boolean',
            'active_instructor' => 'boolean',
            'is_photographer' => 'boolean',
            'air_consumption' => 'float',
            'ease_level' => 'float',
            'total_dives' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
