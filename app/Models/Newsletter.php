<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Newsletter extends Model
{
    protected $fillable = [
        'title', 'month', 'background_image', 'slots', 'status', 'created_by', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'slots' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

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

        return collect($this->slots ?? [])->map(fn ($s) => [
            'position' => $s['position'],
            'article' => $articles->get($s['article_id']),
        ])->filter(fn ($s) => $s['article'])->keyBy('position')->toArray();
    }
}
