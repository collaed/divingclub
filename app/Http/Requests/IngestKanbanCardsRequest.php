<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IngestKanbanCardsRequest extends FormRequest
{
    /** Checked here (not the controller) so an unauthorized caller gets a plain 403, never a validation error. */
    public function authorize(): bool
    {
        $expected = config('kanban.ingest_token');

        return is_string($expected) && $expected !== '' && hash_equals($expected, (string) $this->bearerToken());
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'source_document_name' => 'required|string|max:255',
            'source_document_folder' => 'nullable|string|max:255',
            'source_document_date' => 'nullable|date',
            'actions' => 'required|array|min:1',
            'actions.*.title' => 'required|string|max:255',
            'actions.*.responsible' => 'nullable|string|max:255',
            'actions.*.context' => 'nullable|string|max:2000',
        ];
    }
}
