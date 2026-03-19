<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuardianLink extends Model
{
    use \App\Traits\Auditable;
    protected $guarded = ['id'];

    public function guardian() { return $this->belongsTo(User::class, 'guardian_user_id'); }
    public function minor() { return $this->belongsTo(User::class, 'minor_user_id'); }
}
