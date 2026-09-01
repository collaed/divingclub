<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\ConversationService;
use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A proxied conversation between a Bureau member and an external third party.
 *
 * Replies from the external party arrive at the SAS alias
 * (sas+conv.{token}@domain) and are threaded back to the initiator, keeping the
 * member's real address private.
 *
 * @author  ClubCEP.eu
 *
 * @see     ConversationService
 *
 * @property int $id
 * @property int $initiator_user_id
 * @property int|null $event_id
 * @property string $external_email
 * @property string|null $external_name
 * @property string $token
 * @property string $sas_alias
 * @property string|null $subject
 * @property int $hit_count
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $initiator
 * @property-read Event|null $event
 */
class MailConversation extends Model
{
    use Auditable;
    use HasFactory;

    protected $fillable = [
        'initiator_user_id', 'event_id', 'external_email', 'external_name',
        'token', 'sas_alias', 'subject', 'hit_count', 'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'hit_count' => 'integer',
            'last_activity_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_user_id');
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
