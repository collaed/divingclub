<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\HtmlSanitizer;
use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleImage;
use App\Models\Vote;
use App\Services\ArticleTranslationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    use PaginatesFromRequest;

    public function index(Request $request)
    {
        $query = Article::when($request->type, fn ($q, $t) => $q->where('article_type', $t));

        // Search across title, body, and all translation titles/bodies
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ILIKE', "%{$search}%")
                    ->orWhere('body', 'ILIKE', "%{$search}%")
                    ->orWhereHas('translations', fn ($tq) => $tq->where('title', 'ILIKE', "%{$search}%")
                        ->orWhere('body', 'ILIKE', "%{$search}%"));
            });
        }

        // Sortable columns
        $sortable = ['title', 'article_type', 'is_published', 'is_public', 'expires_at', 'updated_at'];
        $sort = in_array($request->input('sort'), $sortable) ? $request->input('sort') : 'updated_at';
        $dir = $request->input('dir') === 'asc' ? 'asc' : 'desc';

        $articles = $query->orderBy($sort, $dir)->paginate($this->perPage(20))->withQueryString();

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
            'article_type' => 'required|in:'.implode(',', array_keys(Article::TYPES)),
            'is_published' => 'boolean',
            'is_public' => 'boolean',
            'featured_image' => 'nullable|image|max:5120',
            'vote_id' => 'nullable|exists:votes,id',
            'gallery.*' => 'image|max:5120',
            'gallery_captions.*' => 'nullable|string|max:255',
            'gallery_layouts.*' => 'nullable|in:full,half,third',
        ]);

        $validated['slug'] = Str::slug($validated['title']).'-'.Str::random(5);
        $validated['author_id'] = auth()->id();
        $validated['is_published'] = $request->boolean('is_published');
        $validated['is_public'] = $request->boolean('is_public');
        $validated['body'] = HtmlSanitizer::clean($validated['body']);

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
            'article_type' => 'required|in:'.implode(',', array_keys(Article::TYPES)),
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

        $validated['slug'] = Str::slug($validated['title']).'-'.Str::random(5);
        $validated['is_published'] = $request->boolean('is_published');
        $validated['is_public'] = $request->boolean('is_public');
        $validated['body'] = HtmlSanitizer::clean($validated['body']);

        if ($request->hasFile('featured_image')) {
            $validated['featured_image'] = $request->file('featured_image')->store('articles', 'public');
        }

        // Delete selected gallery images
        if ($request->delete_images) {
            ArticleImage::whereIn('id', $request->delete_images)->where('article_id', $article->id)->delete();
        }

        $article->update(collect($validated)->except(['gallery', 'gallery_captions', 'gallery_layouts', 'delete_images'])->toArray());
        $this->storeGallery($request, $article);

        // Mark existing translations as stale (will be re-translated lazily on next access)
        ArticleTranslationService::markStaleIfChanged($article);

        return redirect()->route('admin.articles.edit', $article)->with('success', __('Article updated.'));
    }

    public function destroy(Article $article)
    {
        $article->delete();

        return redirect()->route('admin.articles.index')->with('success', __('Article deleted.'));
    }

    private function storeGallery(Request $request, Article $article): void
    {
        if (! $request->hasFile('gallery')) {
            return;
        }
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
        $locales = config('app.available_locales', ['en', 'fr', 'de', 'lb', 'pt', 'it', 'es', 'nl', 'ro', 'hu', 'sk']);
        $source = $request->input('source_locale', 'fr');
        (new ArticleTranslationService)->translateAll($article, $locales, $source);

        return back()->with('success', __('Translations generated for :count languages.', ['count' => count($locales) - 1]));
    }
}
