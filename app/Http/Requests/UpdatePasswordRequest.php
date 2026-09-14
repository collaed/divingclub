<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            // A socialite-only account has no password to confirm — let it set
            // a first one without asking it to prove knowledge of nothing.
            'current_password' => auth()->user()?->password ? 'required|current_password' : 'nullable',
            'password' => 'required|string|min:8|confirmed',
        ];
    }
}
