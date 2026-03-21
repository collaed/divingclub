<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use Auditable, HasFactory, Notifiable;

    protected $fillable = [
        'username', 'primary_email', 'password', 'role_id', 'status_id', 'email_verified_at', 'preferred_locale',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Auth uses primary_email
    public function getEmailAttribute(): string
    {
        return $this->primary_email;
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->primary_email;
    }

    public function getEmailForVerification(): string
    {
        return $this->primary_email;
    }

    public function getNameAttribute(): string
    {
        $detail = $this->detail;
        if ($detail && $detail->first_name) {
            return trim($detail->first_name.' '.$detail->last_name);
        }

        return $this->username ?? $this->primary_email;
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function status()
    {
        return $this->belongsTo(MemberStatus::class, 'status_id');
    }

    public function emails()
    {
        return $this->hasMany(UserEmail::class);
    }

    public function primaryEmailRecord()
    {
        return $this->hasOne(UserEmail::class)->where('is_primary', true);
    }

    public function socialAccounts()
    {
        return $this->hasMany(UserSocialAccount::class);
    }

    public function detail()
    {
        return $this->hasOne(MemberDetail::class);
    }

    public function licences()
    {
        return $this->hasMany(MemberLicence::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function paymentsExpected()
    {
        return $this->hasMany(PaymentExpected::class);
    }

    public function equipmentLoans()
    {
        return $this->hasMany(EquipmentLoan::class);
    }

    public function gdprConsents()
    {
        return $this->hasMany(GdprConsent::class);
    }

    public function certificationLevels()
    {
        return $this->belongsToMany(CertificationLevel::class, 'user_certification_levels')
            ->withPivot('obtained_date', 'is_primary', 'display_priority')->withTimestamps();
    }

    public function primaryCertification()
    {
        return $this->certificationLevels()->wherePivot('is_primary', true)->first();
    }

    public function hasRole(string $slug): bool
    {
        return $this->role && $this->role->slug === $slug;
    }

    public function hasAnyRole(array $slugs): bool
    {
        return $this->role && in_array($this->role->slug, $slugs);
    }

    public function isBureau(): bool
    {
        return $this->hasAnyRole(['bureau_master', 'bureau_finance', 'bureau_technical']);
    }

    public function isBureauMaster(): bool
    {
        return $this->hasRole('bureau_master');
    }

    // Guardian / minor relationships
    public function guardians()
    {
        return $this->belongsToMany(User::class, 'guardian_links', 'minor_user_id', 'guardian_user_id')
            ->withPivot('relationship')->withTimestamps();
    }

    public function minors()
    {
        return $this->belongsToMany(User::class, 'guardian_links', 'guardian_user_id', 'minor_user_id')
            ->withPivot('relationship')->withTimestamps();
    }

    public function isMinor(): bool
    {
        $dob = $this->detail?->date_of_birth;

        return $dob && $dob->age < 18;
    }

    /** Minors always banned; others check the explicit flag. */
    public function hasPublicPhotosBanned(): bool
    {
        if ($this->isMinor()) {
            return true;
        }

        return (bool) ($this->detail?->public_photos_banned ?? false);
    }

    public function parentalConsents()
    {
        return $this->hasMany(ParentalConsent::class, 'minor_user_id');
    }

    /** Check if profile has the minimum fields needed for dive/pool/training registration. */
    public function hasDiveProfile(): bool
    {
        $d = $this->detail;

        return $d
            && $d->date_of_birth
            && $d->sex
            && $d->phone_mobile
            && $d->emergency_contact_name
            && $d->emergency_contact_phone;
    }

    /** List which required profile fields are still missing. */
    public function missingDiveProfileFields(): array
    {
        $d = $this->detail;
        $missing = [];

        $checks = [
            'date_of_birth' => __('Date of Birth'),
            'sex' => __('Sex'),
            'phone_mobile' => __('Mobile Phone'),
            'emergency_contact_name' => __('Emergency Contact Name'),
            'emergency_contact_phone' => __('Emergency Contact Phone'),
        ];

        foreach ($checks as $field => $label) {
            if (! $d || ! $d->$field) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }
}
