<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\DiveGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDiveGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:100',
            'dive_mode' => 'required|in:supervised,autonomous,training,certification',
            'planned_depth' => 'nullable|integer|min:1|max:300',
            'planned_duration' => 'nullable|integer|min:1|max:300',
            'gas_mix' => 'nullable|in:'.implode(',', array_keys(DiveGroup::GAS_MIXES)),
            'line_number' => 'nullable|integer|min:1|max:4',
            'planned_entry_time' => 'nullable|date_format:H:i',
            'planned_exit_time' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string|max:500',
            'purpose' => 'nullable|string|max:50',
        ];
    }
}
