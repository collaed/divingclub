<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMailAliasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) auth()->user()?->can('send email');
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $ignoreId = $this->route('user')?->mailAlias?->id;

        return [
            'alias' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9.]+$/',
                Rule::unique('mail_aliases', 'alias')->ignore($ignoreId),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'alias.regex' => __('The alias may only contain lowercase letters, digits, and dots.'),
            'alias.unique' => __('This alias is already taken.'),
        ];
    }
}
