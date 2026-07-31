<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDiveGroupRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('manage dive sites');
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'federation_id' => 'required|exists:federations,id',
            'zone' => 'required|string|max:50',
            'min_divers' => 'required|integer|min:1|max:10',
            'max_divers' => 'required|integer|min:1|max:10|gte:min_divers',
            'guide_required' => 'boolean',
            'guide_conditions' => 'nullable|string|max:500',
            'diver_conditions' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
