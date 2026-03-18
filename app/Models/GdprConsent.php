<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class GdprConsent extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['granted' => 'boolean', 'granted_at' => 'datetime', 'revoked_at' => 'datetime']; }
    public function user() { return $this->belongsTo(User::class); }
}
