<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Link;

class HomeController extends Controller
{
    public function index()
    {
        $articles = Article::active()
            ->where('is_public', true)
            ->where('article_type', '!=', 'classified')
            ->with('author.detail')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $links = Link::where('is_public', true)->orderBy('sort_order')->get();

        return view('home', compact('articles', 'links'));
    }

    public function showArticle(string $slug)
    {
        $article = Article::where('slug', $slug)->active()->firstOrFail();

        if (!$article->is_public && !auth()->check()) {
            return redirect()->route('login');
        }

        $extra = [];

        // Dynamic instructor list for the instructors page
        if ($slug === 'instructors') {
            $extra['instructors'] = \App\Models\MemberDetail::whereNotNull('instructor_bio')
                ->where('instructor_bio', '!=', '')
                ->with('user')
                ->get();
        }

        return view('cms.article', compact('article') + $extra);
    }
}
