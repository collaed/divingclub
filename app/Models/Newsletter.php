<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $title
 * @property string|null $month
 * @property string|null $background_image
 * @property array $slots
 * @property array $decorations
 * @property string|null $status
 * @property string|null $created_by
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Newsletter extends Model
{
    protected $fillable = [
        'title', 'month', 'background_image', 'slots', 'decorations', 'status', 'created_by', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'slots' => 'array',
            'decorations' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<NewsletterApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(NewsletterApproval::class);
    }

    public function approvalCount(): int
    {
        return $this->approvals()->where('approved', true)->count();
    }

    public function isApprovedBy(User $user): bool
    {
        return $this->approvals()->where('user_id', $user->id)->where('approved', true)->exists();
    }

    /** Resolve slot articles with translations eager-loaded. */
    public function slotArticles(): array
    {
        $ids = collect($this->slots ?? [])->pluck('article_id')->filter()->unique();
        $articles = Article::with('translations', 'images')->whereIn('id', $ids)->get()->keyBy('id');

        return collect($this->slots ?? [])->map(fn ($s): array => [
            'position' => $s['position'],
            'article' => $articles->get($s['article_id']),
            'teaser' => $s['teaser'] ?? '',
            'custom_url' => $s['custom_url'] ?? '',
            'slug' => $s['slug'] ?? '',
        ])->filter(fn ($s): ?\stdClass => $s['article'])->keyBy('position')->toArray();
    }
}
