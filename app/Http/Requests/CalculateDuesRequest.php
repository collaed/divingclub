<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a dues calculation request. There is deliberately no age-vs-status
 * check: age never selects a status (a child is a Membre de droit or an
 * Externe member), it only drives the under-18 share of the cotisation and the
 * FFESSM licence band in FeeCalculationService.
 */
class CalculateDuesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'season_year' => 'required|string|max:10',
            'status_id' => 'required|integer|exists:member_statuses,id',
            'last_name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'optionals' => 'array',
            'optionals.*' => 'string',
        ];
    }
}
