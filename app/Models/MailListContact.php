<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * An external contact (no User account) who can be picked into a mail
 * alias's recipient list — see MailAliasDefinition::include_contact_ids and
 * MailAliasService::resolveDefinition(). unsubscribe_token powers a public,
 * no-login unsubscribe link (MailListUnsubscribeController) that flips
 * subscribed to false; only subscribed contacts are ever included.
 *
 * @property int $id
 * @property string $email
 * @property string|null $name
 * @property bool $subscribed
 * @property string $unsubscribe_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MailListContact extends Model
{
    protected $fillable = ['email', 'name', 'subscribed'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (MailListContact $contact): void {
            $contact->unsubscribe_token ??= Str::random(40);
        });
    }

    protected function casts(): array
    {
        return [
            'subscribed' => 'boolean',
        ];
    }
}
