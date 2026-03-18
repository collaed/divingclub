<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class VoteToken extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['is_consumed' => 'boolean', 'consumed_at' => 'datetime']; }
    public function vote() { return $this->belongsTo(Vote::class); }
    public function user() { return $this->belongsTo(User::class); }
}
