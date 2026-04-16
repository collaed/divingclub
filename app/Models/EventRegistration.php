<?php

/**
 * Event registration model with full audit trail.
 *
 * Tracks who registered, who cancelled, and why — supporting both self-registration
 * and proxy registration (bureau/instructor registering on behalf of a member).
 * Includes waiting list positioning and check-in/check-out timestamps for events.
 *
 * @author  ClubCEP.eu
 *
 * @see     \App\Http\Controllers\EventController  — registration/cancellation logic
 */

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRegistration extends Model
{
    use Auditable;

    protected $fillable = [
        'joomla_inscription_id', 'event_id', 'user_id', 'status', 'comment',
        'registered_by', 'waiting_list_position',
        'cancelled_at', 'cancelled_by', 'cancel_comment',
        'checked_in_at', 'checked_out_at', 'checked_in_by',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Who performed the registration (null = self-registration). */
    public function registeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /** Who cancelled the registration (null = self-cancellation). */
    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
