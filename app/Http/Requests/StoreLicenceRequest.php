<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLicenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) auth()->user()?->can('manage members');
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'federation_id' => [
                'required',
                'integer',
                'exists:federations,id',
                Rule::unique('member_licences', 'federation_id')->where(
                    fn ($query) => $query->where('user_id', $userId)
                ),
            ],
            'licence_number' => 'nullable|string|max:50',
            'season' => 'nullable|string|max:20',
            'insurance_type' => 'nullable|string|max:50',
            'licence_request_date' => 'nullable|date',
            'licence_request_pending' => 'boolean',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'federation_id.unique' => __('This member already has a licence for that federation. Edit the existing one instead.'),
        ];
    }
}
