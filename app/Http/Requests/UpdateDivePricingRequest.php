<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDivePricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isBureau() ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'dive_unit_price' => 'required|numeric|min:0',
            'nitrox_supplement' => 'required|numeric|min:0',
            'instructor_daily_subsidy' => 'required|numeric|min:0',
            'dive_days' => 'nullable|integer|min:1|max:30',
        ];
    }
}
