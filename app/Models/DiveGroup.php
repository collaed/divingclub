<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiveGroup extends Model
{
    protected $guarded = ['id'];

    public function event() { return $this->belongsTo(Event::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function members() { return $this->hasMany(DiveGroupMember::class); }
    public function leader() { return $this->members()->where('role', 'leader')->first(); }
    public function divers() { return $this->members()->where('role', 'diver'); }

    public const DIVE_MODES = ['supervised', 'autonomous', 'training', 'certification'];
}
