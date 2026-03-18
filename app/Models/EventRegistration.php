<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class EventRegistration extends Model
{
    use Auditable;

    protected $fillable = ['event_id', 'user_id', 'status', 'waiting_list_position', 'checked_in_at', 'checked_out_at', 'checked_in_by'];

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime', 'checked_out_at' => 'datetime'];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
