<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $article_id
 * @property string|null $file_path
 * @property string|null $alt_text
 * @property string|null $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ArticleImage extends Model
{
    protected $fillable = ['article_id', 'file_path', 'alt_text', 'sort_order'];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
