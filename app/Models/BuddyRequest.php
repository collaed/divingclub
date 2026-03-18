<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuddyRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['dive_date' => 'date', 'is_active' => 'boolean'];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function diveSite() { return $this->belongsTo(DiveSite::class); }
    public function responses() { return $this->hasMany(BuddyResponse::class); }

    public function scopeActive($q) { return $q->where('is_active', true)->where('dive_date', '>=', today()); }

    public function locationLabel(): string
    {
        return $this->diveSite?->name ?? $this->location_text ?? '—';
    }

    public const NEED_TYPES = [
        'buddy' => '🤝 Buddy',
        'guide' => '👑 Guide de Palanquée / Divemaster',
        'dp' => '📋 Directeur de Plongée',
    ];
}
