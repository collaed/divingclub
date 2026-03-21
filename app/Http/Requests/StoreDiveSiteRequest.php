<?php

namespace App\Http\Requests;

use App\Models\DiveSite;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDiveSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isBureauMaster();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'country' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'max_depth' => 'nullable|integer|min:1|max:300',
            'water_type' => 'nullable|in:'.implode(',', DiveSite::WATER_TYPES),
            'conditions' => 'nullable|string',
            'marine_life' => 'nullable|string',
            'safety_notes' => 'nullable|string',
            'access_notes' => 'nullable|string',
            'facilities' => 'nullable|string',
            'food_options' => 'nullable|string',
            'nearest_hospital' => 'nullable|string',
            'website_url' => 'nullable|url|max:500',
            'entry_fee' => 'nullable|numeric|min:0',
            'booking_url' => 'nullable|url|max:500',
            'image' => 'nullable|image|max:5120',
            'map_image' => 'nullable|image|max:5120',
            'site_plan' => 'nullable|file|mimes:jpg,jpeg,png,gif,svg,pdf|max:10240',
            'safety_docs_folder' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }
}
