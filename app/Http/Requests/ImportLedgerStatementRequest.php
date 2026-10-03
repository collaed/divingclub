<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportLedgerStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        // 5MB is generous for a bank statement export (even years of transactions
        // stay well under this) and keeps the limit under SonarCloud's 8MB
        // permissive-upload-size threshold (php:S5693).
        return ['statement' => 'required|file|mimes:xlsx|max:5120'];
    }
}
