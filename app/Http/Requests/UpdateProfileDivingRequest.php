<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileDivingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'dive_count' => 'nullable|integer|min:0',
            'total_dives' => 'nullable|integer|min:0',
            'last_dive_date' => 'nullable|date',
            'air_consumption' => 'nullable|numeric|min:0|max:1',
            'ease_level' => 'nullable|numeric|min:0|max:1',
            'primary_intent' => 'nullable|string|in:exploration,photography,training,deep,wreck,night,drift',
            'is_photographer' => 'nullable|boolean',
            'certification_level' => 'nullable|string|max:50',
            'apnea_level' => 'nullable|string|max:50',
            'other_certifications' => 'nullable|string',
            'training_enrollments' => 'nullable|string',
        ];
    }
}
