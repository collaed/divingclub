<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $type
 * @property string|null $icon
 * @property string|null $label
 * @property string|null $link_type
 * @property int|null $article_id
 * @property string|null $target
 * @property bool|null $auth_required
 * @property array<int, string>|null $roles
 * @property int $sort_order
 * @property-read MenuItem|null $parent
 * @property-read Collection<int, MenuItem> $children
 * @property-read Article|null $article
 */
class MenuItem extends Model
{
    protected $fillable = [
        'parent_id', 'type', 'icon', 'label', 'link_type',
        'article_id', 'target', 'auth_required', 'roles', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'auth_required' => 'boolean',
            'roles' => 'array',
        ];
    }

    /** @return BelongsTo<MenuItem, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<MenuItem, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /** @return Collection<int, MenuItem> */
    public static function forNav(): Collection
    {
        return static::whereNull('parent_id')
            ->with(['children.article.translations'])
            ->orderBy('sort_order')
            ->get();
    }

    public function resolvedLabel(?string $locale = null): ?string
    {
        if ($this->type === 'divider') {
            return null;
        }

        if ($this->link_type === 'article') {
            return $this->label !== null ? __($this->label) : $this->article?->translated($locale)['title'];
        }

        return $this->label === null ? null : __($this->label);
    }

    public function resolvedUrl(): string
    {
        return match (true) {
            $this->type !== 'link' => '#',
            $this->link_type === 'article' && $this->article => route('article.show', $this->article->slug),
            $this->link_type === 'route' => Route::has((string) $this->target) ? route($this->target) : '#',
            $this->link_type === 'url' => (string) $this->target,
            default => '#',
        };
    }

    public function isVisibleTo(?User $user): bool
    {
        if ($this->auth_required === true && ! $user) {
            return false;
        }

        if ($this->auth_required === false && $user) {
            return false;
        }

        if ($this->roles) {
            return $user !== null && $user->hasAnyRole($this->roles);
        }

        return true;
    }

    public function isCurrent(): bool
    {
        if ($this->type === 'dropdown') {
            return $this->children->contains(fn (self $child) => $child->isCurrent());
        }

        if ($this->type !== 'link') {
            return false;
        }

        if ($this->link_type === 'route') {
            return request()->routeIs((string) $this->target);
        }

        if ($this->link_type === 'article' && $this->article) {
            return request()->routeIs('article.show') && request()->route('slug') === $this->article->slug;
        }

        return false;
    }
}
