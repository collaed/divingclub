<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberLicence extends Model
{
    protected $fillable = ['user_id', 'federation_id', 'licence_number', 'federation_key', 'licence_request_date', 'licence_request_pending', 'insurance_type', 'medical_cert_expiry', 'season', 'registration_date'];

    protected function casts(): array
    {
        return ['licence_request_date' => 'date', 'licence_request_pending' => 'boolean', 'medical_cert_expiry' => 'date', 'registration_date' => 'date'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function federation()
    {
        return $this->belongsTo(Federation::class);
    }
}
