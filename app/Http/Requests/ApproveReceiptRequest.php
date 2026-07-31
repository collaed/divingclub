<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApproveReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isBureau() ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'approved_amount' => 'required|numeric|min:0',
            'category' => 'required|in:general,transit,diving,individual,memo',
            'reviewer_notes' => 'nullable|string|max:500',
        ];
    }
}
