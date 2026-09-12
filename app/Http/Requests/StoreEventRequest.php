<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('manage events');
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            // No color_hex: an event's colour is derived from its type
            // (config/activity_types.php via Event::color()).
            'event_type' => 'required|in:pool,dive,training,theory,social',
            'event_date' => 'required|date',
            'event_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'end_date' => 'nullable|date|after_or_equal:event_date',
            'location' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'responsible_id' => 'nullable|exists:users,id',
            'max_participants' => 'nullable|integer|min:1',
            'waiting_list_enabled' => 'boolean',
            'inscription_open_at' => 'nullable|date',
            'inscription_close_at' => 'nullable|date',
            'registration_mode' => 'nullable|in:'.implode(',', Event::REGISTRATION_MODES),
            'inscriptions_closed' => 'boolean',
            'levels_display' => 'boolean',
            'confirmation_required' => 'boolean',
            'estimated_cost' => 'nullable|numeric|min:0',
            'deposit_1_date' => 'nullable|date',
            'deposit_1_amount' => 'nullable|numeric|min:0',
            'deposit_2_date' => 'nullable|date',
            'deposit_2_amount' => 'nullable|numeric|min:0',
            'deposit_3_date' => 'nullable|date',
            'deposit_3_amount' => 'nullable|numeric|min:0',
            'instructor_id' => 'nullable|exists:users,id',
            'permissions_expire_date' => 'nullable|date',
            'status' => 'nullable|in:scheduled,published,cancelled,completed',
            'season_id' => 'nullable|exists:seasons,id',
            'dive_site_id' => 'nullable|exists:dive_sites,id',
        ];
    }
}
