<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileLanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'preferred_language' => 'required|in:en,fr,de,it,es,pt,nl,pl,ro,cs,el,lb',
            'show_icons' => 'nullable|in:0,1',
        ];
    }
}
