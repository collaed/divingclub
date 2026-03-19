<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\EventPhoto;
use App\Models\Link;
use App\Models\MemberDetail;

class HomeController extends Controller
{
    public function index()
    {
        $articles = Article::active()
            ->where('is_public', true)
            ->where('article_type', '!=', 'classified')
            ->where('sort_order', '>=', 0)
            ->with('author.detail')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $links = Link::where('is_public', true)->orderBy('sort_order')->get();

        // Best photos for hero — no faces for anonymous visitors, all for members
        $heroPhotos = auth()->check()
            ? EventPhoto::bestForMembers(8)->get()
            : EventPhoto::bestPublic(8)->get();

        return view('home', compact('articles', 'links', 'heroPhotos'));
    }

    public function showArticle(string $slug)
    {
        $article = Article::where('slug', $slug)->active()->with('translations')->firstOrFail();

        if (! $article->is_public && ! auth()->check()) {
            return redirect()->route('login');
        }

        $extra = [];

        // Dynamic instructor list for the instructors page
        if ($slug === 'instructors') {
            $extra['instructors'] = MemberDetail::whereNotNull('instructor_bio')
                ->where('instructor_bio', '!=', '')
                ->with('user')
                ->get();
        }

        // Available translation locales for tab UI
        $extra['translatedLocales'] = $article->translations->pluck('locale')->toArray();

        return view('cms.article', compact('article') + $extra);
    }
}
