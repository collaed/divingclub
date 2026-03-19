<?php

/**
 * Email communication log linked to events.
 *
 * Stores inbound and outbound emails associated with events (via the per-event
 * participant email address). Used to display communication history on the
 * event detail page.
 *
 * @author  ClubCEP.eu
 *
 * @see     \App\Http\Controllers\EventController::show()
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $table = 'email_log';

    protected $guarded = ['id'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
