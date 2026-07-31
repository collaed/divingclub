<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreClassifiedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:5000',
            'category' => 'required|string|in:sale,wanted,buddy,other',
            'price' => 'nullable|numeric|min:0',
            'featured_image' => 'nullable|image|max:5120',
        ];
    }
}
