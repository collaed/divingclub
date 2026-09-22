<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One imported bank line. `state` is the inbox classification (see
 * LedgerClassificationService): "expected" (deep green, matches a known
 * amount), "recognised" (light green, known but nothing to check against),
 * "confirm" (amber, probable or mismatched), "unknown" (red), "loop" (part
 * of a detected pass-through pair).
 *
 * @property int $id
 * @property Carbon $transaction_date
 * @property Carbon|null $value_date
 * @property string $amount
 * @property string|null $running_balance
 * @property string|null $statement_no
 * @property string|null $operation_type
 * @property string|null $communication_1
 * @property string|null $communication_2
 * @property string|null $communication_3
 * @property string|null $communication_4
 * @property string|null $beneficiary_account
 * @property string|null $counterparty_name
 * @property string|null $counterparty_address
 * @property string|null $counterparty_locality
 * @property int|null $counterparty_id
 * @property string|null $category
 * @property string $state
 * @property string|null $state_reason
 * @property string|null $suggested_group
 * @property Carbon|null $confirmed_at
 * @property int|null $confirmed_by
 * @property string|null $source_file
 * @property string $dedup_hash
 * @property int|null $imported_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class LedgerTransaction extends Model
{
    public const STATE_EXPECTED = 'expected';

    public const STATE_RECOGNISED = 'recognised';

    public const STATE_CONFIRM = 'confirm';

    public const STATE_UNKNOWN = 'unknown';

    public const STATE_LOOP = 'loop';

    protected $fillable = [
        'transaction_date', 'value_date', 'amount', 'running_balance', 'statement_no', 'operation_type',
        'communication_1', 'communication_2', 'communication_3', 'communication_4',
        'beneficiary_account', 'counterparty_name', 'counterparty_address', 'counterparty_locality', 'counterparty_id',
        'category', 'state', 'state_reason', 'suggested_group',
        'confirmed_at', 'confirmed_by', 'source_file', 'dedup_hash', 'imported_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date', 'value_date' => 'date', 'confirmed_at' => 'datetime',
            'amount' => 'decimal:2', 'running_balance' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<LedgerCounterparty, $this> */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(LedgerCounterparty::class, 'counterparty_id');
    }

    /**
     * Usually one, but exceptionally more than one — e.g. one van-rental invoice
     * split between two separate outings.
     *
     * @return BelongsToMany<LedgerOperation, $this>
     */
    public function operations(): BelongsToMany
    {
        return $this->belongsToMany(LedgerOperation::class, 'ledger_operation_transaction')->withTimestamps();
    }

    /** @return BelongsToMany<LedgerTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(LedgerTag::class, 'ledger_transaction_tag')->withPivot('value')->withTimestamps();
    }

    /** @return BelongsTo<User, $this> */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** Every non-empty communication line joined, for keyword matching and display. */
    public function communication(): string
    {
        return collect([$this->communication_1, $this->communication_2, $this->communication_3, $this->communication_4])
            ->filter()->implode(' ');
    }

    public function scopeUnconfirmed(Builder $query): void
    {
        $query->whereNull('confirmed_at');
    }

    /** Already-reviewed lines — for going back to check or undo a past confirm. */
    public function scopeConfirmed(Builder $query): void
    {
        $query->whereNotNull('confirmed_at');
    }
}
