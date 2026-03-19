<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParentalConsent extends Model
{
    use \App\Traits\Auditable;
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['granted' => 'boolean', 'granted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function minor() { return $this->belongsTo(User::class, 'minor_user_id'); }
    public function grantedBy() { return $this->belongsTo(User::class, 'granted_by'); }
}
