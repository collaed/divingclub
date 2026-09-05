<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSeasonPatternRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isBureau() ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $types = implode(',', array_keys(config('activity_types', [])));

        return [
            'day_of_week' => 'required|integer|min:0|max:6',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'event_type' => 'required|in:'.$types,
            'title' => 'required|string|max:255',
            'location' => 'nullable|string|max:500',
            'max_participants' => 'nullable|integer|min:1',
            'registration_opens_days_before' => 'nullable|integer|min:1',
            'color_hex' => 'nullable|string|max:7',
        ];
    }
}
