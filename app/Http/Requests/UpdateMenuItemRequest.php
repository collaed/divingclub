<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Only the fields the inline admin UI actually lets you change after
 * creation (icon/label/roles/sort_order for an item, label/auth_required
 * for a dropdown) — every rule is "sometimes" because each PATCH only
 * carries the one row's own fields, not the whole record. Structural
 * fields (type/link_type/article_id/target) are fixed at creation time
 * (see StoreMenuItemRequest) and not editable through this endpoint.
 */
class UpdateMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) auth()->user()?->can('manage articles');
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $menuItemId = $this->route('menuItem')?->id;

        return [
            'parent_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('menu_items', 'id')->where('type', 'dropdown')->whereNull('parent_id'),
                Rule::notIn([$menuItemId]),
            ],
            'icon' => 'sometimes|nullable|string|max:10',
            'label' => 'sometimes|nullable|string|max:255',
            'auth_required' => 'sometimes|nullable|boolean',
            'roles' => 'sometimes|nullable|array',
            'roles.*' => 'string|exists:roles,name',
            'sort_order' => 'sometimes|integer',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'parent_id.exists' => __('The parent must be an existing top-level dropdown — menus can only be two levels deep.'),
        ];
    }
}
