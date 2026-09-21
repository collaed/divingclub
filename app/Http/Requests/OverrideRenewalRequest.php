<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OverrideRenewalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'season_year' => 'required|string|max:10',
            'method' => 'required|in:cash,transfer,other',
            'amount' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:255',
        ];
    }
}
