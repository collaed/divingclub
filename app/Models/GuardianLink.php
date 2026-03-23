<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class GuardianLink extends Model
{
    use Auditable;

    protected $fillable = ['guardian_user_id', 'minor_user_id', 'relationship'];

    public function guardian()
    {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }

    public function minor()
    {
        return $this->belongsTo(User::class, 'minor_user_id');
    }
}
