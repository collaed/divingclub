<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $country
 * @property string|null $region
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $max_depth
 * @property string|null $water_type
 * @property string|null $conditions
 * @property string|null $marine_life
 * @property string|null $safety_notes
 * @property string|null $access_notes
 * @property string|null $facilities
 * @property string|null $food_options
 * @property string|null $nearest_hospital
 * @property string|null $emergency_phone
 * @property string|null $vhf_channel
 * @property string|null $required_safety_equipment
 * @property string|null $nearest_hyperbaric_chamber
 * @property string|null $hyperbaric_phone
 * @property string|null $hospital_distance_km
 * @property string|null $hyperbaric_distance_km
 * @property string|null $website_url
 * @property string|null $entry_fee
 * @property string|null $booking_url
 * @property string|null $image_path
 * @property string|null $map_image_path
 * @property string|null $site_plan_path
 * @property string|null $safety_docs_folder
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DiveSite extends Model
{
    protected $fillable = ['name', 'country', 'region', 'latitude', 'longitude', 'max_depth', 'water_type', 'conditions', 'marine_life', 'safety_notes', 'access_notes', 'facilities', 'food_options', 'nearest_hospital', 'emergency_phone', 'vhf_channel', 'required_safety_equipment', 'nearest_hyperbaric_chamber', 'hyperbaric_phone', 'hospital_distance_km', 'hyperbaric_distance_km', 'website_url', 'entry_fee', 'booking_url', 'image_path', 'map_image_path', 'site_plan_path', 'safety_docs_folder', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
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
