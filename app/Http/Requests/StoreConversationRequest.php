<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) auth()->user()?->can('send email');
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'external_email' => 'required|email|max:255',
            'external_name' => 'nullable|string|max:255',
            'subject' => 'required|string|max:200',
            'message' => 'required|string|max:10000',
            'event_id' => 'nullable|exists:events,id',
        ];
    }
}
