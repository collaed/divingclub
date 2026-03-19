<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventPhoto extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['approved' => 'boolean', 'gdpr_consent' => 'boolean'];
    }

    public function event() { return $this->belongsTo(Event::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function socialPublishLogs() { return $this->morphMany(SocialPublishLog::class, 'publishable'); }
}
