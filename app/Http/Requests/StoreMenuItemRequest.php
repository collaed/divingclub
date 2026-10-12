<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

class StoreMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) auth()->user()?->can('manage articles');
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('menu_items', 'id')->where('type', 'dropdown')->whereNull('parent_id'),
            ],
            'type' => 'required|string|in:dropdown,link,divider',
            'icon' => 'nullable|string|max:10',
            'label' => [
                Rule::requiredIf(fn () => $this->input('type') !== 'divider' && ! ($this->input('type') === 'link' && $this->input('link_type') === 'article')),
                'nullable', 'string', 'max:255',
            ],
            'link_type' => 'required_if:type,link|nullable|string|in:article,route,url',
            'article_id' => 'required_if:link_type,article|nullable|integer|exists:articles,id',
            'target' => [
                Rule::requiredIf(fn () => in_array($this->input('link_type'), ['route', 'url'], true)),
                'nullable', 'string', 'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->input('link_type') === 'route' && $value && ! Route::has($value)) {
                        $fail(__('No route named ":route" exists.', ['route' => $value]));
                    }
                },
            ],
            'auth_required' => 'nullable|boolean',
            'roles' => 'nullable|array',
            'roles.*' => 'string|exists:roles,name',
            'sort_order' => 'integer',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'parent_id.exists' => __('The parent must be an existing top-level dropdown — menus can only be two levels deep.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('type') === 'dropdown' || $this->input('type') === 'divider') {
            $this->merge(['link_type' => null, 'article_id' => null, 'target' => null]);
        }
    }
}
