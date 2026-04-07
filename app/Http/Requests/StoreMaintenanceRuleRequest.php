<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('manage equipment');
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'equipment_type' => 'required|string|max:100',
            'maintenance_name' => 'required|string|max:255',
            'interval_months' => 'required|integer|min:1',
            'is_mandatory' => 'boolean',
            'regulation_reference' => 'nullable|string|max:255',
        ];
    }
}
