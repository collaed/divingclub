<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('bureau_master') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'library_file_id' => 'required|integer|exists:library_files,id',
            'subject' => 'required|string|max:255',
            'message' => 'nullable|string|max:5000',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'integer|exists:users,id',
        ];
    }
}
