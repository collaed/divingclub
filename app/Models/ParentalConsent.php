<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class ParentalConsent extends Model
{
    use Auditable;

    protected $fillable = ['minor_user_id', 'granted_by', 'consent_type', 'granted', 'document_path', 'granted_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['granted' => 'boolean', 'granted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function minor()
    {
        return $this->belongsTo(User::class, 'minor_user_id');
    }

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
