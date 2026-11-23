<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin-managed inbound redirect: mail arriving at `from_address` is
 * forwarded unconditionally to `to_addresses`, bypassing the normal
 * alias/sender-authorization checks in MailAliasService — for public-facing
 * addresses (e.g. the contact mailbox) where the sender is never a club
 * member. See PollInboundMail::processMessage().
 *
 * A row with `user_id` set is a member's personal vanity alias
 * (`sas.{name}@...`) rather than a general-purpose redirect — see
 * MemberController::activatePersonalAlias().
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $from_address
 * @property string $to_addresses
 * @property string|null $label
 * @property bool $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MailForwardingRule extends Model
{
    protected $fillable = ['user_id', 'from_address', 'to_addresses', 'label', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    /** @return string[] */
    public function targets(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->to_addresses))));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Last time mail to this rule's address was actually forwarded — see PollInboundMail::forwardViaRule(). */
    public function lastForwardedAt(): ?Carbon
    {
        return EmailLog::where('alias', $this->from_address)->latest()->value('created_at');
    }
}
