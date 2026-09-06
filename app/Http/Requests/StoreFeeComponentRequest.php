<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFeeComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('manage settings') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'is_base' => 'boolean',
            'is_optional' => 'boolean',
            'prorata_eligible' => 'boolean',
            'taper_below_age' => 'nullable|integer|min:0|max:120',
            'taper_ratio' => 'nullable|numeric|min:0|max:1',
            'age_anchor_date' => 'nullable|date',
            'description' => 'nullable|string',
        ];
    }
}
