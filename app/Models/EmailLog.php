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

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $event_id
 * @property int|null $user_id
 * @property string|null $to_email
 * @property string|null $alias
 * @property string|null $from_name
 * @property string|null $from_email
 * @property string|null $subject
 * @property string|null $body
 * @property string|null $template_slug
 * @property string|null $status
 * @property string|null $direction
 * @property string|null $authorized
 * @property string|null $attempts
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EmailLog extends Model
{
    protected $table = 'email_log';

    protected $fillable = ['event_id', 'user_id', 'to_email', 'alias', 'from_name', 'from_email', 'subject', 'body', 'template_slug', 'status', 'direction', 'authorized', 'attempts', 'error'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
