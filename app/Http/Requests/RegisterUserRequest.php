<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:user_emails,email|unique:users,primary_email',
            'password' => ['required', 'confirmed', Password::defaults()],
            'date_of_birth' => 'required|date|before:today',
            'sex' => 'required|in:M,F,X',
            'phone_mobile' => 'required|string|max:20',
            'nationality' => 'nullable|string|max:100',
            'address_line1' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'website' => 'size:0',
            '_ts' => 'required|integer',
        ];
    }
}
