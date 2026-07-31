<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BureauReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isBureau() ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        /** @var Event $event */
        $event = $this->route('event');
        $participantIds = $event->tripParticipants()->whereNotNull('user_id')->pluck('user_id')->toArray();
        $category = $this->input('category');

        $userRule = $category === 'individual'
            ? 'required|integer|in:'.implode(',', $participantIds)
            : 'nullable|integer|in:'.implode(',', $participantIds);

        return [
            'amount' => 'required|numeric|min:0.01|max:99999',
            'category' => 'required|in:general,transit,diving,individual,memo',
            'description' => 'required|string|max:255',
            'user_id' => $userRule,
            'is_third_party' => 'nullable|boolean',
        ];
    }
}
