<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMedicalRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('manage settings');
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'federation_id' => 'required|exists:federations,id',
            'age_bracket_low' => 'required|integer|min:0',
            'age_bracket_high' => 'required|integer|min:0|gte:age_bracket_low',
            'cert_type' => 'required|string|in:gp,ent,cardio,ophthalmologist,other',
            'validity_months' => 'required|integer|min:1',
        ];
    }
}
