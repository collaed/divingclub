<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $iban
 * @property string $kind
 * @property int|null $member_id
 * @property string|null $default_category
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class LedgerCounterparty extends Model
{
    public const KIND_MEMBER = 'member';

    public const KIND_SUPPLIER = 'supplier';

    public const KIND_FEDERATION = 'federation';

    public const KIND_VENUE = 'venue';

    public const KIND_OTHER = 'other';

    protected $fillable = ['name', 'iban', 'kind', 'member_id', 'default_category', 'notes'];

    /** @return BelongsTo<User, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    /** @return HasMany<LedgerTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(LedgerTransaction::class, 'counterparty_id');
    }
}
