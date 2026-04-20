<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $article_id
 * @property string|null $locale
 * @property string|null $title
 * @property string|null $body
 * @property bool $auto_translated
 * @property bool $stale
 * @property string|null $source_hash
 * @property string|null $source_word_count
 * @property string|null $translated_word_count
 * @property string|null $retries
 * @property Carbon|null $flagged_at
 * @property string|null $flag_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ArticleTranslation extends Model
{
    protected $fillable = [
        'article_id', 'locale', 'title', 'body', 'auto_translated', 'stale',
        'source_hash', 'source_word_count', 'translated_word_count', 'retries', 'flagged_at', 'flag_reason',
    ];

    protected function casts(): array
    {
        return [
            'auto_translated' => 'boolean',
            'stale' => 'boolean',
            'flagged_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /** Check if word count ratio is plausible (translation shouldn't be <30% or >300% of source). */
    public function hasPlausibleWordCount(): bool
    {
        if (! $this->source_word_count || ! $this->translated_word_count) {
            return true;
        }
        $ratio = $this->translated_word_count / max($this->source_word_count, 1);

        return $ratio >= 0.3 && $ratio <= 3.0;
    }
}
