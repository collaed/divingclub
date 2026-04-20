<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $type
 * @property int|null $event_id
 * @property string|null $season_year
 * @property string|null $amount_due
 * @property string|null $communication
 * @property array $components
 * @property string|null $status
 * @property string|null $refund_review_needed
 * @property string|null $amount_paid
 * @property Carbon|null $paid_at
 * @property string|null $reconciled_by
 * @property Carbon|null $reconciled_at
 * @property string|null $bank_statement_ref
 * @property Carbon|null $bank_statement_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PaymentExpected extends Model
{
    protected $table = 'payment_expected';

    protected $fillable = ['user_id', 'type', 'event_id', 'season_year', 'amount_due', 'communication', 'components', 'status', 'refund_review_needed', 'amount_paid', 'paid_at', 'reconciled_by', 'reconciled_at', 'bank_statement_ref', 'bank_statement_date'];

    protected function casts(): array
    {
        return ['components' => 'array', 'paid_at' => 'date', 'reconciled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
