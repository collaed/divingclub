<?php

namespace App\Http\Controllers;

use App\Helpers\HtmlSanitizer;
use App\Http\Requests\StoreCommentRequest;
use App\Models\Article;
use App\Models\ArticleComment;
use Illuminate\Http\RedirectResponse;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Article $article): RedirectResponse
    {
        ArticleComment::create([
            'article_id' => $article->id,
            'user_id' => auth()->id(),
            'parent_id' => $request->parent_id,
            'body' => HtmlSanitizer::clean($request->body, 'comment'),
        ]);

        return back()->with('success', __('Comment posted.'));
    }

    public function destroy(ArticleComment $comment): RedirectResponse
    {
        abort_unless($comment->user_id === auth()->id() || auth()->user()->can('manage articles'), 403);
        $comment->delete();

        return back()->with('success', __('Comment deleted.'));
    }
}
