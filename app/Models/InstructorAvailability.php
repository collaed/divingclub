<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstructorAvailability extends Model
{
    protected $fillable = ['user_id', 'date', 'slot', 'activity_type', 'note'];

    protected $casts = ['date' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
