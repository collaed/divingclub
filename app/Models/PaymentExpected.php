<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentExpected extends Model
{
    protected $table = 'payment_expected';

    protected $fillable = ['user_id', 'type', 'event_id', 'season_year', 'amount_due', 'communication', 'components', 'status', 'refund_review_needed', 'amount_paid', 'paid_at', 'reconciled_by', 'reconciled_at', 'bank_statement_ref', 'bank_statement_date'];

    protected function casts(): array
    {
        return ['components' => 'array', 'paid_at' => 'date', 'reconciled_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
