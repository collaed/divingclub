<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberDetail extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $fillable = ['user_id', 'avatar_path', 'first_name', 'last_name', 'birth_name', 'nationality', 'phone_private', 'phone_office', 'phone_mobile', 'sex', 'adhesion_year', 'bureau_member', 'active_instructor', 'instructor_bio', 'instructor_specialties', 'instructor_motivation', 'show_on_public_site', 'public_photos_banned', 'club_email', 'date_of_birth', 'place_of_birth', 'address_line1', 'address_line2', 'city', 'postal_code', 'country', 'iban', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship', 'brevet_date', 'dive_count', 'air_consumption', 'ease_level', 'primary_intent', 'is_photographer', 'total_dives', 'last_dive_date', 'certification_level', 'other_certifications', 'training_enrollments', 'preferred_language', 'show_icons', 'cotisation_years', 'bcd_size', 'bcd_notes'];

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
