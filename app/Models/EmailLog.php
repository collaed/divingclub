<?php

declare(strict_types=1);

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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    use HasFactory;

    protected $table = 'email_log';

    protected $fillable = ['event_id', 'user_id', 'to_email', 'alias', 'from_name', 'from_email', 'subject', 'body', 'template_slug', 'status', 'direction', 'authorized', 'attempts', 'error', 'provider'];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * One inbound alias message produces one EmailLog row per recipient
     * (see PollInboundMail::processMessage()) — these share the same
     * subject/sender/timestamp and are really copies of a single
     * communication. Used to collapse them into one thread for display
     * (EventController::show()) and to apply an edit to every copy at once
     * (EmailController::updateLog()).
     */
    public function threadKey(): string
    {
        return md5(($this->subject ?? '').'|'.($this->from_email ?? '').'|'.($this->created_at?->format('Y-m-d H:i:s') ?? ''));
    }

    /** @param  Builder<EmailLog>  $query */
    public function scopeSameThread(Builder $query, self $log): void
    {
        $query->where('subject', $log->subject)
            ->where('from_email', $log->from_email)
            ->where('created_at', $log->created_at);
    }
}
