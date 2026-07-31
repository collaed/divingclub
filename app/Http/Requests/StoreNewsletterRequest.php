<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreNewsletterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isBureau() ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'month' => 'required|string|max:7',
            'background_image' => 'nullable|image|max:5120',
            'background_preset' => 'nullable|string|max:50',
            'slots' => 'required|array|min:1',
            'slots.*.position' => 'required|integer|between:1,5',
            'slots.*.article_id' => 'required|exists:articles,id',
            'slots.*.article_type' => 'nullable|string|max:30',
            'slots.*.teaser' => 'nullable|string|max:500',
            'slots.*.custom_url' => 'nullable|string|max:500',
            'slots.*.slug' => 'nullable|string|max:255',
        ];
    }
}
