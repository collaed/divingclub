<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFederationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isBureauMaster();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'acronym' => 'required|string|max:20|unique:federations,acronym'.($this->route('federation') ? ','.$this->route('federation')->id : ''),
            'full_name' => 'required|string|max:255',
            'visibility' => 'sometimes|in:active,recognized,invisible',
        ];
    }
}
