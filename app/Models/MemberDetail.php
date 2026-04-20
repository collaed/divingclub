<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $avatar_path
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $birth_name
 * @property string|null $nationality
 * @property string|null $phone_private
 * @property string|null $phone_office
 * @property string|null $phone_mobile
 * @property string|null $sex
 * @property string|null $adhesion_year
 * @property bool $bureau_member
 * @property bool $active_instructor
 * @property string|null $instructor_bio
 * @property string|null $instructor_specialties
 * @property string|null $instructor_motivation
 * @property string|null $show_on_public_site
 * @property string|null $public_photos_banned
 * @property string|null $club_email
 * @property Carbon|null $date_of_birth
 * @property string|null $place_of_birth
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $city
 * @property string|null $postal_code
 * @property string|null $country
 * @property string|null $iban
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_phone
 * @property string|null $emergency_contact_relationship
 * @property Carbon|null $brevet_date
 * @property string|null $dive_count
 * @property float $air_consumption
 * @property float $ease_level
 * @property string|null $primary_intent
 * @property bool $is_photographer
 * @property int $total_dives
 * @property Carbon|null $last_dive_date
 * @property string|null $certification_level
 * @property array $other_certifications
 * @property array $training_enrollments
 * @property string|null $preferred_language
 * @property string|null $show_icons
 * @property array $cotisation_years
 * @property string|null $bcd_size
 * @property string|null $bcd_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
