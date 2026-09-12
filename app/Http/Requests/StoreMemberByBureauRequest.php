<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bureau-side exception to normal self-registration: e.g. an older member
 * with no email, or someone joined on paper. Deliberately lighter than
 * RegisterUserRequest — the bureau may not have every field on hand yet and
 * can fill the rest in from the profile afterwards.
 */
class StoreMemberByBureauRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isBureau() ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:user_emails,email|unique:users,primary_email',
            'status_id' => 'nullable|exists:member_statuses,id',
            'date_of_birth' => 'nullable|date|before:today',
            'phone_mobile' => 'nullable|string|max:20',
            'nationality' => ['nullable', 'string', Rule::in(config('countries.all'))],
        ];
    }
}
