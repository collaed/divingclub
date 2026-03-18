<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberLicence extends Model
{
    protected $fillable = ['user_id', 'federation_id', 'licence_number', 'federation_key', 'licence_request_date', 'licence_request_pending'];

    protected function casts(): array
    {
        return ['licence_request_date' => 'date', 'licence_request_pending' => 'boolean'];
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
