<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use Auditable, HasFactory, HasRoles, Notifiable;
    use SoftDeletes;

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

    public function legacyRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(MemberStatus::class, 'status_id');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(UserEmail::class);
    }

    public function primaryEmailRecord(): HasOne
    {
        return $this->hasOne(UserEmail::class)->where('is_primary', true);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(UserSocialAccount::class);
    }

    public function detail(): HasOne
    {
        return $this->hasOne(MemberDetail::class);
    }

    public function licences(): HasMany
    {
        return $this->hasMany(MemberLicence::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function paymentsExpected(): HasMany
    {
        return $this->hasMany(PaymentExpected::class);
    }

    public function eventRegistrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function equipmentLoans(): HasMany
    {
        return $this->hasMany(EquipmentLoan::class);
    }

    public function gdprConsents(): HasMany
    {
        return $this->hasMany(GdprConsent::class);
    }

    public function certificationLevels(): BelongsToMany
    {
        return $this->belongsToMany(CertificationLevel::class, 'user_certification_levels')
            ->withPivot('obtained_date', 'is_primary', 'display_priority')->withTimestamps();
    }

    public function primaryCertification(): ?CertificationLevel
    {
        return $this->certificationLevels()->wherePivot('is_primary', true)->first();
    }

    public function isBureau(): bool
    {
        return $this->hasAnyRole(['bureau_master', 'bureau_finance', 'bureau_technical']);
    }

    /** @deprecated Use isBureau() or $user->can('permission') for granular checks. */
    public function isBureauMaster(): bool
    {
        return $this->isBureau();
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guardian_links', 'minor_user_id', 'guardian_user_id')
            ->withPivot('relationship')->withTimestamps();
    }

    public function minors(): BelongsToMany
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

    public function parentalConsents(): HasMany
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
