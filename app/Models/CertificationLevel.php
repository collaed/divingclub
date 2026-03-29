<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CertificationLevel extends Model
{
    protected $fillable = ['federation_id', 'code', 'name', 'category', 'rank', 'equivalence_group'];

    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_certification_levels')->withPivot('obtained_date', 'is_primary', 'display_priority')->withTimestamps();
    }

    // Get equivalent certs across federations
    public function equivalents()
    {
        if (! $this->equivalence_group) {
            return collect();
        }

        return static::where('equivalence_group', $this->equivalence_group)->where('id', '!=', $this->id)->with('federation')->get();
    }

    public function label(): string
    {
        return $this->code.' ('.$this->federation?->acronym.')';
    }
}
