<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, Auditable;

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
            return trim($detail->first_name . ' ' . $detail->last_name);
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

    public function parentalConsents()
    {
        return $this->hasMany(ParentalConsent::class, 'minor_user_id');
    }
}
