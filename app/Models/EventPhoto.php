<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventPhoto extends Model
{
    protected $guarded = ['id'];

    public function event() { return $this->belongsTo(Event::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
}
