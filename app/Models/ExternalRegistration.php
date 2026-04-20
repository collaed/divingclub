<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $event_id
 * @property int|null $partnership_id
 * @property string|null $external_member_name
 * @property string|null $external_member_email
 * @property string|null $external_member_phone
 * @property string|null $external_member_federation
 * @property string|null $external_member_licence_no
 * @property string|null $external_member_emergency_contact
 * @property string|null $external_member_iban
 * @property string|null $external_cert_level
 * @property Carbon|null $external_medical_valid_until
 * @property string|null $status
 * @property string|null $notes
 * @property string|null $external_ref
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ExternalRegistration extends Model
{
    protected $fillable = ['event_id', 'partnership_id', 'external_member_name', 'external_member_email', 'external_member_phone', 'external_member_federation', 'external_member_licence_no', 'external_member_emergency_contact', 'external_member_iban', 'external_cert_level', 'external_medical_valid_until', 'status', 'notes', 'external_ref'];

    protected $casts = [
        'external_medical_valid_until' => 'date',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function partnership(): BelongsTo
    {
        return $this->belongsTo(ClubPartnership::class, 'partnership_id');
    }
}
