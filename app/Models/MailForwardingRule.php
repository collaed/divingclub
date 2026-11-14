<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * An admin-managed inbound redirect: mail arriving at `from_address` is
 * forwarded unconditionally to `to_addresses`, bypassing the normal
 * alias/sender-authorization checks in MailAliasService — for public-facing
 * addresses (e.g. the contact mailbox) where the sender is never a club
 * member. See PollInboundMail::processMessage().
 *
 * @property int $id
 * @property string $from_address
 * @property string $to_addresses
 * @property string|null $label
 * @property bool $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MailForwardingRule extends Model
{
    protected $fillable = ['from_address', 'to_addresses', 'label', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    /** @return string[] */
    public function targets(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->to_addresses))));
    }
}
