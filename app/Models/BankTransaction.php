<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class BankTransaction extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['transaction_date' => 'date']; }
    public function matchedPayment() { return $this->belongsTo(PaymentExpected::class, 'matched_payment_id'); }
    public function confirmedByUser() { return $this->belongsTo(User::class, 'confirmed_by'); }
}
