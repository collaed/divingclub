<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMembershipFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('manage payments');
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'season_year' => 'required|string|max:10',
            'status_id' => 'required|exists:member_statuses,id',
            'amount' => 'required|numeric|min:0',
            'label' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ];
    }
}
