<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadEventPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'photos.*' => 'required|file|max:102400|mimes:jpg,jpeg,png,gif,webp,heic,heif,mp4,mov,avi,webm,zip',
            'caption' => 'nullable|string|max:255',
            'gdpr_consent' => 'required|accepted',
        ];
    }
}
