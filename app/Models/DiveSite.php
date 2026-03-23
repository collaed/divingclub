<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiveSite extends Model
{
    protected $fillable = ['name', 'country', 'region', 'latitude', 'longitude', 'max_depth', 'water_type', 'conditions', 'marine_life', 'safety_notes', 'access_notes', 'facilities', 'food_options', 'nearest_hospital', 'emergency_phone', 'vhf_channel', 'required_safety_equipment', 'nearest_hyperbaric_chamber', 'hyperbaric_phone', 'hospital_distance_km', 'hyperbaric_distance_km', 'website_url', 'entry_fee', 'booking_url', 'image_path', 'map_image_path', 'site_plan_path', 'safety_docs_folder', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function mapsUrl(): string
    {
        if ($this->latitude && $this->longitude) {
            return 'https://www.google.com/maps/search/?api=1&query='.$this->latitude.','.$this->longitude;
        }

        return 'https://www.google.com/maps/search/'.urlencode($this->name.' '.($this->region ?? '').' '.($this->country ?? ''));
    }

    public const WATER_TYPES = ['sea', 'lake', 'quarry', 'river', 'pool', 'cenote'];
}
