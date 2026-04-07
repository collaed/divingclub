<?php

namespace App\Http\Requests;

use App\Models\Article;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('manage articles');
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'article_type' => 'required|in:'.implode(',', array_keys(Article::TYPES)),
            'is_published' => 'boolean',
            'is_public' => 'boolean',
            'featured_image' => 'nullable|image|max:5120',
            'vote_id' => 'nullable|exists:votes,id',
            'gallery.*' => 'image|max:5120',
            'gallery_captions.*' => 'nullable|string|max:255',
            'gallery_layouts.*' => 'nullable|in:full,half,third',
            'delete_images' => 'nullable|array',
            'delete_images.*' => 'exists:article_images,id',
        ];
    }
}
