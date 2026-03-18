<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiveSite extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }

    public function events() { return $this->hasMany(Event::class); }

    public function scopeActive($q) { return $q->where('is_active', true); }

    public function mapsUrl(): string
    {
        if ($this->latitude && $this->longitude) {
            return 'https://www.google.com/maps/search/?api=1&query=' . $this->latitude . ',' . $this->longitude;
        }
        return 'https://www.google.com/maps/search/' . urlencode($this->name . ' ' . ($this->region ?? '') . ' ' . ($this->country ?? ''));
    }

    public const WATER_TYPES = ['sea', 'lake', 'quarry', 'river', 'pool', 'cenote'];
}
