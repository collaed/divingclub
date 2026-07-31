<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecordPrepaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isBureau() ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'participant_id' => 'required|integer',
            'amount' => 'required|numeric|min:0',
        ];
    }
}
