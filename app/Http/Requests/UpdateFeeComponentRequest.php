<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Partial update for a fee component — used by inline AJAX auto-save, so every
 * field is optional (`sometimes`) and only the changed fields are sent.
 */
class UpdateFeeComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('manage settings') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:100',
            'amount' => 'sometimes|required|numeric|min:0',
            'is_base' => 'sometimes|boolean',
            'is_optional' => 'sometimes|boolean',
            'prorata_eligible' => 'sometimes|boolean',
            'taper_below_age' => 'sometimes|nullable|integer|min:0|max:120',
            'taper_ratio' => 'sometimes|nullable|numeric|min:0|max:1',
            'age_anchor_date' => 'sometimes|nullable|date',
            'description' => 'sometimes|nullable|string',
        ];
    }
}
