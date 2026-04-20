<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property Carbon|null $transaction_date
 * @property string|null $amount
 * @property string|null $communication
 * @property string|null $counterparty
 * @property int|null $matched_payment_id
 * @property string|null $match_score
 * @property string|null $status
 * @property string|null $statement_ref
 * @property string|null $confirmed_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BankTransaction extends Model
{
    protected $fillable = ['transaction_date', 'amount', 'communication', 'counterparty', 'matched_payment_id', 'match_score', 'status', 'statement_ref', 'confirmed_by'];

    protected function casts(): array
    {
        return ['transaction_date' => 'date'];
    }

    public function matchedPayment(): BelongsTo
    {
        return $this->belongsTo(PaymentExpected::class, 'matched_payment_id');
    }

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
