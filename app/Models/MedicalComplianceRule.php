<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $federation_id
 * @property string|null $age_bracket_low
 * @property string|null $age_bracket_high
 * @property string|null $cert_type
 * @property string|null $validity_months
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MedicalComplianceRule extends Model
{
    protected $fillable = ['federation_id', 'age_bracket_low', 'age_bracket_high', 'cert_type', 'validity_months'];

    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }
}
