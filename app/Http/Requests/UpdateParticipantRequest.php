<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isBureau() ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'driving_percentage' => 'required|integer|min:0|max:100',
            'local_transit_days' => 'required|integer|min:0|max:60',
            'transit_mode' => 'nullable|in:van,own,fly',
            'van_number' => 'nullable|integer|min:1|max:10',
            'is_supervising_instructor' => 'nullable|boolean',
            'supervising_days' => 'nullable|integer|min:0|max:30',
        ];
    }
}
