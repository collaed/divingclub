<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class MembershipFeeComponent extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['is_base' => 'boolean', 'is_optional' => 'boolean']; }
    public function season() { return $this->belongsTo(Season::class); }
}
