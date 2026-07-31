<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'file' => 'required|file|mimetypes:application/pdf,image/jpeg,image/png|max:10240',
            'category' => 'required|string|in:certification,medical,insurance,other',
            'date_established' => 'nullable|date',
        ];
    }
}
