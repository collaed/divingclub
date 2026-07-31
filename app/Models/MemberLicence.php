<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $federation_id
 * @property string|null $licence_number
 * @property string|null $federation_key
 * @property Carbon|null $licence_request_date
 * @property bool $licence_request_pending
 * @property string|null $insurance_type
 * @property Carbon|null $medical_cert_expiry
 * @property string|null $season
 * @property Carbon|null $registration_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MemberLicence extends Model
{
    protected $fillable = ['user_id', 'federation_id', 'licence_number', 'federation_key', 'licence_request_date', 'licence_request_pending', 'insurance_type', 'medical_cert_expiry', 'season', 'registration_date'];

    protected function casts(): array
    {
        return ['licence_request_date' => 'date', 'licence_request_pending' => 'boolean', 'medical_cert_expiry' => 'date', 'registration_date' => 'date'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Federation, $this> */
    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }
}
