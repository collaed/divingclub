<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleTranslation extends Model
{
    protected $fillable = ['article_id', 'locale', 'title', 'body', 'auto_translated', 'stale'];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
