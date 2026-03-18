<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleImage;
use App\Models\Vote;
use App\Services\ArticleTranslationService;
use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    private function purify(string $html): string
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'h2,h3,p,br,strong,b,em,i,u,s,a[href|target],ul,ol,li,blockquote,img[src|alt|style],span[style]');
        $config->set('HTML.TargetBlank', true);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        return (new HTMLPurifier($config))->purify($html);
    }

    public function index(Request $request)
    {
        $articles = Article::when($request->type, fn ($q, $t) => $q->where('article_type', $t))
            ->orderByDesc('updated_at')->paginate(20);
        return view('admin.articles.index', compact('articles'));
    }

    public function create(Request $request)
    {
        $votes = Vote::where('status', 'open')->orWhere('status', 'draft')->orderByDesc('created_at')->get();
        return view('admin.articles.form', ['article' => new Article(['article_type' => $request->get('type', 'news')]), 'votes' => $votes]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'article_type' => 'required|in:' . implode(',', array_keys(Article::TYPES)),
            'is_published' => 'boolean',
            'is_public' => 'boolean',
            'featured_image' => 'nullable|image|max:5120',
            'vote_id' => 'nullable|exists:votes,id',
            'gallery.*' => 'image|max:5120',
            'gallery_captions.*' => 'nullable|string|max:255',
            'gallery_layouts.*' => 'nullable|in:full,half,third',
        ]);

        $validated['slug'] = Str::slug($validated['title']) . '-' . Str::random(5);
        $validated['author_id'] = auth()->id();
        $validated['is_published'] = $request->boolean('is_published');
        $validated['is_public'] = $request->boolean('is_public');
        $validated['body'] = $this->purify($validated['body']);

        if ($request->hasFile('featured_image')) {
            $validated['featured_image'] = $request->file('featured_image')->store('articles', 'public');
        }

        $article = Article::create(collect($validated)->except(['gallery', 'gallery_captions', 'gallery_layouts'])->toArray());
        $this->storeGallery($request, $article);

        return redirect()->route('admin.articles.index')->with('success', __('Article created.'));
    }

    public function edit(Article $article)
    {
        $votes = Vote::where('status', 'open')->orWhere('status', 'draft')->orderByDesc('created_at')->get();
        return view('admin.articles.form', compact('article', 'votes'));
    }

    public function update(Request $request, Article $article)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'article_type' => 'required|in:' . implode(',', array_keys(Article::TYPES)),
            'is_published' => 'boolean',
            'is_public' => 'boolean',
            'featured_image' => 'nullable|image|max:5120',
            'vote_id' => 'nullable|exists:votes,id',
            'gallery.*' => 'image|max:5120',
            'gallery_captions.*' => 'nullable|string|max:255',
            'gallery_layouts.*' => 'nullable|in:full,half,third',
            'delete_images' => 'nullable|array',
            'delete_images.*' => 'exists:article_images,id',
        ]);

        $validated['slug'] = Str::slug($validated['title']) . '-' . Str::random(5);
        $validated['is_published'] = $request->boolean('is_published');
        $validated['is_public'] = $request->boolean('is_public');
        $validated['body'] = $this->purify($validated['body']);

        if ($request->hasFile('featured_image')) {
            $validated['featured_image'] = $request->file('featured_image')->store('articles', 'public');
        }

        // Delete selected gallery images
        if ($request->delete_images) {
            ArticleImage::whereIn('id', $request->delete_images)->where('article_id', $article->id)->delete();
        }

        $article->update(collect($validated)->except(['gallery', 'gallery_captions', 'gallery_layouts', 'delete_images'])->toArray());
        $this->storeGallery($request, $article);

        return redirect()->route('admin.articles.index')->with('success', __('Article updated.'));
    }

    public function destroy(Article $article)
    {
        $article->delete();
        return redirect()->route('admin.articles.index')->with('success', __('Article deleted.'));
    }

    private function storeGallery(Request $request, Article $article): void
    {
        if (!$request->hasFile('gallery')) return;
        $maxSort = $article->images()->max('sort_order') ?? 0;
        foreach ($request->file('gallery') as $i => $file) {
            ArticleImage::create([
                'article_id' => $article->id,
                'file_path' => $file->store('articles/gallery', 'public'),
                'alt_text' => $request->input("gallery_captions.$i"),
                'caption' => $request->input("gallery_captions.$i"),
                'layout_hint' => $request->input("gallery_layouts.$i", 'full'),
                'sort_order' => ++$maxSort,
            ]);
        }
    }

    public function translate(Request $request, Article $article)
    {
        $locales = config('app.available_locales', ['en','fr','de','lb','pt','it','es','nl','ro','hu','sk']);
        $source = $request->input('source_locale', 'fr');
        (new ArticleTranslationService)->translateAll($article, $locales, $source);
        return back()->with('success', __('Translations generated for :count languages.', ['count' => count($locales) - 1]));
    }
}
