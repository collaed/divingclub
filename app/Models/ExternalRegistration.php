<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalRegistration extends Model
{
    protected $guarded = [];

    protected $casts = [
        'external_medical_valid_until' => 'date',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function partnership()
    {
        return $this->belongsTo(ClubPartnership::class, 'partnership_id');
    }
}
