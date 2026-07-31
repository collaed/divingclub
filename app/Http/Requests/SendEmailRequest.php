<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('send email');
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'template_id' => 'required|exists:email_templates,id',
            'group' => 'required|in:all,active,instructors,bureau,expiring_certs,unpaid,event',
            'event_id' => 'nullable|required_if:group,event|exists:events,id',
        ];
    }
}
