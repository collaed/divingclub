<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificationLevel extends Model
{
    protected $guarded = ['id'];

    public function federation() { return $this->belongsTo(Federation::class); }
    public function users() { return $this->belongsToMany(User::class, 'user_certification_levels')->withPivot('obtained_date', 'is_primary', 'display_priority')->withTimestamps(); }

    // Get equivalent certs across federations
    public function equivalents()
    {
        if (!$this->equivalence_group) return collect();
        return static::where('equivalence_group', $this->equivalence_group)->where('id', '!=', $this->id)->with('federation')->get();
    }

    public function label(): string { return $this->code . ' (' . $this->federation?->acronym . ')'; }
}
