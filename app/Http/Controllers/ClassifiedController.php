<?php

namespace App\Http\Controllers;

use App\Helpers\HtmlSanitizer;
use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClassifiedController extends Controller
{
    use PaginatesFromRequest;

    public function index(Request $request)
    {
        $classifieds = Article::where('article_type', 'classified')
            ->active()->where('is_published', true)
            ->with('author.detail')
            ->when($request->input('search'), fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('title', 'like', "%{$s}%")
                    ->orWhere('body', 'like', "%{$s}%");
            }))
            ->orderByDesc('created_at')->paginate($this->perPage(20))->withQueryString();
        $mine = Article::where('article_type', 'classified')
            ->where('author_id', auth()->id())
            ->orderByDesc('created_at')->get();

        return view('classifieds.index', compact('classifieds', 'mine'));
    }

    public function create()
    {
        return view('classifieds.form', ['article' => new Article(['article_type' => 'classified'])]);
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'featured_image' => 'nullable|image|max:5120',
        ]);

        $v['slug'] = Str::slug($v['title']).'-'.Str::random(5);
        $v['article_type'] = 'classified';
        $v['author_id'] = auth()->id();
        $v['is_published'] = true;
        $v['is_public'] = false;
        $v['expires_at'] = now()->addDays(30);
        $v['body'] = HtmlSanitizer::clean($v['body'], 'basic');

        if ($request->hasFile('featured_image')) {
            $v['featured_image'] = $request->file('featured_image')->store('classifieds', 'public');
        }

        Article::create($v);

        return redirect()->route('classifieds.index')->with('success', __('Classified posted. It will expire in 30 days.'));
    }

    public function edit(Article $article)
    {
        abort_unless($article->article_type === 'classified' && $article->author_id === auth()->id(), 403);

        return view('classifieds.form', compact('article'));
    }

    public function update(Request $request, Article $article)
    {
        abort_unless($article->article_type === 'classified' && $article->author_id === auth()->id(), 403);

        $v = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'featured_image' => 'nullable|image|max:5120',
        ]);

        $v['body'] = HtmlSanitizer::clean($v['body'], 'basic');

        if ($request->hasFile('featured_image')) {
            $v['featured_image'] = $request->file('featured_image')->store('classifieds', 'public');
        }

        $article->update($v);

        return redirect()->route('classifieds.index')->with('success', __('Classified updated.'));
    }

    public function extend(Article $article)
    {
        abort_unless($article->article_type === 'classified' && $article->author_id === auth()->id(), 403);
        $article->update(['expires_at' => now()->addDays(30)]);

        return back()->with('success', __('Extended for 30 more days.'));
    }

    public function destroy(Article $article)
    {
        abort_unless(
            $article->article_type === 'classified' && ($article->author_id === auth()->id() || auth()->user()->can('manage articles')),
            403
        );
        $article->delete();

        return redirect()->route('classifieds.index')->with('success', __('Classified deleted.'));
    }
}
