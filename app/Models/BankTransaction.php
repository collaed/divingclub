<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
