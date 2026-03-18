<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberDetail extends Model
{
    use \App\Traits\Auditable;
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'brevet_date' => 'date',
            'other_certifications' => 'array',
            'training_enrollments' => 'array',
            'cotisation_years' => 'array',
            'bureau_member' => 'boolean',
            'active_instructor' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
