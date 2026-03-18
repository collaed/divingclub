<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleImage extends Model
{
    protected $fillable = ['article_id', 'file_path', 'alt_text', 'sort_order'];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
