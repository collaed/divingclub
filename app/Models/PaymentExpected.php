<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PaymentExpected extends Model
{
    protected $table = 'payment_expected';
    protected $guarded = ['id'];
    protected function casts(): array { return ['components' => 'array', 'paid_at' => 'date', 'reconciled_at' => 'datetime']; }
    public function user() { return $this->belongsTo(User::class); }
    public function event() { return $this->belongsTo(Event::class); }
}
