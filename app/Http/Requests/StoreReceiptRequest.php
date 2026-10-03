<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01|max:99999',
            'category' => 'required|in:general,transit,diving,individual,memo',
            'description' => 'nullable|string|max:255',
            // 5MB is generous for a receipt photo/scan and keeps the limit under
            // SonarCloud's 8MB permissive-upload-size threshold (php:S5693).
            'image' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ];
    }
}
