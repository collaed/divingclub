<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\Article;
use App\Models\ArticleComment;
use HTMLPurifier;
use HTMLPurifier_Config;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Article $article)
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,br,strong,b,em,i,a[href]');
        $clean = (new HTMLPurifier($config))->purify($request->body);

        ArticleComment::create([
            'article_id' => $article->id,
            'user_id' => auth()->id(),
            'parent_id' => $request->parent_id,
            'body' => $clean,
        ]);

        return back()->with('success', __('Comment posted.'));
    }

    public function destroy(ArticleComment $comment)
    {
        abort_unless($comment->user_id === auth()->id() || auth()->user()->isBureauMaster(), 403);
        $comment->delete();

        return back()->with('success', __('Comment deleted.'));
    }
}
