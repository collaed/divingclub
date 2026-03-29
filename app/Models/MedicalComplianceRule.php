<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalComplianceRule extends Model
{
    protected $fillable = ['federation_id', 'age_bracket_low', 'age_bracket_high', 'cert_type', 'validity_months'];

    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }
}
