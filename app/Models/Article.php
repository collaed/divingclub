<?php

namespace App\Models;

use App\Helpers\HtmlSanitizer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string|null $title
 * @property string|null $slug
 * @property string|null $body
 * @property string|null $article_type
 * @property string|null $featured_image
 * @property bool $is_published
 * @property bool $is_public
 * @property int|null $author_id
 * @property int|null $vote_id
 * @property Carbon|null $expires_at
 * @property string|null $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $author
 * @property-read Collection $translations
 */
class Article extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'body', 'article_type', 'featured_image',
        'is_published', 'is_public', 'author_id', 'vote_id', 'expires_at', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_public' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public const TYPES = [
        'news' => ['icon' => '📰', 'color' => '#0d6efd', 'label' => 'News'],
        'history' => ['icon' => '🏛️', 'color' => '#6f42c1', 'label' => 'Club History'],
        'safety' => ['icon' => '🛟', 'color' => '#dc3545', 'label' => 'Safety'],
        'training' => ['icon' => '🎓', 'color' => '#198754', 'label' => 'Training'],
        'regulation' => ['icon' => '📋', 'color' => '#6c757d', 'label' => 'Regulation'],
        'trip_report' => ['icon' => '🌊', 'color' => '#0dcaf0', 'label' => 'Trip Report'],
        'trip_proposal' => ['icon' => '🗺️', 'color' => '#fd7e14', 'label' => 'Trip Proposal'],
        'environment' => ['icon' => '🌿', 'color' => '#20c997', 'label' => 'Environment'],
        'gear' => ['icon' => '🤿', 'color' => '#0077be', 'label' => 'Gear'],
        'classified' => ['icon' => '🏷️', 'color' => '#ffc107', 'label' => 'Classified'],
        'faq' => ['icon' => '❓', 'color' => '#adb5bd', 'label' => 'FAQ'],
        'newsletter' => ['icon' => '📬', 'color' => '#e83e8c', 'label' => 'Newsletter'],
        'video' => ['icon' => '🎬', 'color' => '#e74c3c', 'label' => 'Video'],
    ];

    public const MEMBER_TYPES = ['classified'];

    public function typeMeta(): array
    {
        return self::TYPES[$this->article_type] ?? self::TYPES['news'];
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Render body with auto-embedded YouTube/Vimeo links.
     * Sanitizes HTML first, then applies embed transformations.
     */
    public function renderedBody(): string
    {
        $body = HtmlSanitizer::clean($this->body ?? '');

        // YouTube: convert bare URLs to responsive embeds
        $body = preg_replace(
            '#(?:<a[^>]*>)?\s*(?:https?://)?(?:www\.)?(?:youtube\.com/watch\?v=|youtu\.be/)([\w-]+)[^\s<]*\s*(?:</a>)?#i',
            '<div class="ratio ratio-16x9 mb-3"><iframe src="https://www.youtube.com/embed/$1" allowfullscreen loading="lazy"></iframe></div>',
            $body
        );
        // Vimeo
        $body = preg_replace(
            '#(?:<a[^>]*>)?\s*(?:https?://)?(?:www\.)?vimeo\.com/(\d+)[^\s<]*\s*(?:</a>)?#i',
            '<div class="ratio ratio-16x9 mb-3"><iframe src="https://player.vimeo.com/video/$1" allowfullscreen loading="lazy"></iframe></div>',
            $body
        );

        return $body;
    }

    public function canBeEditedBy(mixed $user): bool
    {
        if ($user->can('manage articles')) {
            return true;
        }

        return in_array($this->article_type, self::MEMBER_TYPES) && $this->author_id === $user->id;
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<Vote, $this> */
    public function vote(): BelongsTo
    {
        return $this->belongsTo(Vote::class);
    }

    /** @return HasMany<ArticleImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ArticleImage::class)->orderBy('sort_order');
    }

    /** @return HasMany<ArticleComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(ArticleComment::class);
    }

    /** @return HasMany<ArticleTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(ArticleTranslation::class);
    }

    /**
     * Get translated title/body for a locale, falling back to original.
     */
    public function translated(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $t = $this->translations->firstWhere('locale', $locale);

        return [
            'title' => $t?->title ?? $this->title,
            'body' => $t?->body ?? $this->body,
            'locale' => $t ? $locale : null,
            'auto' => $t?->auto_translated ?? false,
        ];
    }

    /** @return HasMany<ArticleComment, $this> */
    public function rootComments(): HasMany
    {
        return $this->hasMany(ArticleComment::class)->whereNull('parent_id')->orderBy('created_at');
    }

    public function previousInType(): ?self
    {
        return self::where('article_type', $this->article_type)
            ->active()->where('created_at', '<', $this->created_at)
            ->orderByDesc('created_at')->first();
    }

    public function nextInType(): ?self
    {
        return self::where('article_type', $this->article_type)
            ->active()->where('created_at', '>', $this->created_at)
            ->orderBy('created_at')->first();
    }

    public function previousOverall(): ?self
    {
        return self::active()->where('created_at', '<', $this->created_at)
            ->orderByDesc('created_at')->first();
    }

    public function nextOverall(): ?self
    {
        return self::active()->where('created_at', '>', $this->created_at)
            ->orderBy('created_at')->first();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
