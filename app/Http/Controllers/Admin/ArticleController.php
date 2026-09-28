<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Helpers\HtmlSanitizer;
use App\Helpers\LocaleHelper;
use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Http\Controllers\Controller;
use App\Jobs\TranslateArticle;
use App\Models\Article;
use App\Models\ArticleImage;
use App\Models\Vote;
use App\Services\ArticleTranslationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class ArticleController extends Controller
{
    use PaginatesFromRequest;

    public function index(Request $request): RedirectResponse|View
    {
        $query = Article::when($request->type, fn ($q, $t) => $q->where('article_type', $t));

        // Search across title, body, and all translation titles/bodies
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search): void {
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

    public function create(Request $request): RedirectResponse|View
    {
        $votes = Vote::where('status', 'open')->orWhere('status', 'draft')->orderByDesc('created_at')->get();

        return view('admin.articles.form', ['article' => new Article(['article_type' => $request->get('type', 'news')]), 'votes' => $votes]);
    }

    public function store(Request $request): RedirectResponse|View
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

    public function edit(Article $article): RedirectResponse|View
    {
        $votes = Vote::where('status', 'open')->orWhere('status', 'draft')->orderByDesc('created_at')->get();

        return view('admin.articles.form', compact('article', 'votes'));
    }

    public function update(Request $request, Article $article): RedirectResponse
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

        // Mark existing translations as stale, then re-translate after 2 min debounce
        ArticleTranslationService::markStaleIfChanged($article);
        TranslateArticle::dispatch($article->id, ArticleTranslationService::sourceHash($article))
            ->delay(now()->addMinutes(2));

        return redirect()->route('admin.articles.edit', $article)->with('success', __('Article updated.'));
    }

    public function togglePublish(Request $request, Article $article): RedirectResponse|JsonResponse
    {
        $article->update(['is_published' => ! $article->is_published]);

        if ($request->ajax()) {
            return response()->json(['is_published' => $article->is_published]);
        }

        return redirect()->route('admin.articles.index')->with('success', __('Article updated.'));
    }

    public function destroy(Article $article): RedirectResponse
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

    public function translate(Request $request, Article $article): RedirectResponse
    {
        $locales = LocaleHelper::enabledLocales();
        $source = $request->input('source_locale', 'fr');
        (new ArticleTranslationService)->translateAll($article, $locales, $source);

        return back()->with('success', __('Translations generated for :count languages.', ['count' => count($locales) - 1]));
    }

    /**
     * Status grid: every enabled locale, plus the French original, with an
     * at-a-glance state (original / manually edited / auto-translated /
     * stale / flagged / missing) and a link to edit it.
     */
    public function translations(Article $article): View
    {
        $locales = collect(LocaleHelper::enabledLocales())
            ->reject(fn (string $locale): bool => $locale === 'fr')
            ->values();
        $translations = $article->translations->keyBy('locale');

        return view('admin.articles.translations.index', compact('article', 'locales', 'translations'));
    }

    /**
     * Edit one locale's title/body directly — the French original is edited
     * on the normal article form instead, since it IS the Article row.
     */
    public function editTranslation(Article $article, string $locale): RedirectResponse|View
    {
        if ($locale === 'fr') {
            return redirect()->route('admin.articles.edit', $article);
        }

        $translation = $article->translations()->where('locale', $locale)->first();

        return view('admin.articles.translations.edit', compact('article', 'locale', 'translation'));
    }

    /**
     * Save a human-curated translation. Marked auto_translated=false so no
     * automatic pass (ProcessTranslations, the bulk "Generate translations"
     * button, this article's own next save) ever silently overwrites it —
     * see ArticleTranslationService::translate().
     */
    public function updateTranslation(Request $request, Article $article, string $locale): RedirectResponse
    {
        abort_if($locale === 'fr', 404);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $article->translations()->updateOrCreate(['locale' => $locale], [
            'title' => $validated['title'],
            'body' => HtmlSanitizer::clean($validated['body']),
            'auto_translated' => false,
            'stale' => false,
            'source_hash' => ArticleTranslationService::sourceHash($article),
            'retries' => 0,
            'flagged_at' => null,
            'flag_reason' => null,
        ]);

        return redirect()->route('admin.articles.translations.edit', [$article, $locale])
            ->with('success', __('Translation saved.'));
    }

    /**
     * Discard a manually-curated (or stale/flagged) translation and regenerate
     * it via the AI provider. force:true is what lets this override a manual
     * edit — the confirmation dialog is the UI's job (see the edit view).
     */
    public function regenerateTranslation(Article $article, string $locale): RedirectResponse
    {
        abort_if($locale === 'fr', 404);

        (new ArticleTranslationService)->translate($article, $locale, 'fr', force: true);

        return redirect()->route('admin.articles.translations.edit', [$article, $locale])
            ->with('success', __('Translation regenerated.'));
    }

    /**
     * Inline image upload for the rich editor (TinyMCE images_upload_handler).
     * Re-encoded through Intervention rather than stored as-is: this strips
     * EXIF/metadata and neutralizes malformed or polyglot image payloads, and
     * downscales anything oversized instead of trusting whatever the
     * contributor's camera/phone produced.
     */
    public function uploadImage(Request $request, Article $article): JsonResponse
    {
        $request->validate([
            'file' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
        ]);

        $file = $request->file('file');
        $mime = $file->getMimeType();
        $extension = match ($mime) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $image = Image::decode($file->getContent())->scaleDown(width: 1600);
        $encoded = match ($extension) {
            'png' => $image->encodeUsingMediaType('image/png'),
            'gif' => $image->encodeUsingMediaType('image/gif'),
            'webp' => $image->encodeUsingMediaType('image/webp', quality: 85),
            default => $image->encodeUsingMediaType('image/jpeg', quality: 85),
        };

        // Never trust the uploaded filename (path traversal, homograph tricks,
        // double extensions) — a fresh random name plus the extension we just
        // derived from the re-encoded content is the only name that's used.
        $path = 'articles/inline/'.$article->id.'/'.Str::random(24).'.'.$extension;
        // Storage::put() (unlike EncodedImage::save($absolutePath)) creates any
        // missing parent directories — needed here since this per-article
        // folder won't exist yet for a first upload.
        Storage::disk('public')->put($path, (string) $encoded);

        return response()->json(['location' => Storage::disk('public')->url($path)]);
    }
}
