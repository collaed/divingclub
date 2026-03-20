<?php

/**
 * Public-facing homepage and CMS article rendering.
 *
 * index() loads the configurable widget layout (hero, articles, events, etc.)
 * with per-widget visibility filtering. showArticle() renders CMS pages with
 * auto-translation, stale refresh, and live member statistics for the
 * member-figures slug.
 *
 * @author ClubCEP.eu
 */

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\MemberDetail;
use App\Services\ArticleTranslationService;

class HomeController extends Controller
{
    public function index()
    {
        $layout = HomepageLayoutController::getLayout();
        $widgetTypes = HomepageLayoutController::widgetTypes();
        $user = auth()->user();

        // Load data for each enabled + visible widget
        $widgets = collect($layout)->map(function ($w) use ($user) {
            if (! ($w['enabled'] ?? false)) {
                return $w;
            }
            if (! HomepageLayoutController::isVisibleTo($w, $user)) {
                $w['hidden_by_role'] = true;

                return $w;
            }
            $w['data'] = HomepageLayoutController::loadWidgetData($w);

            return $w;
        });

        $isAdmin = $user?->isBureauMaster();

        return view('home', compact('widgets', 'widgetTypes', 'isAdmin'));
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
                ->where('show_on_public_site', true)
                ->with('user')
                ->get();
        }

        // Live member statistics charts
        if ($slug === 'member-figures') {
            $details = MemberDetail::whereHas('user', fn ($q) => $q->whereNotNull('status_id'))->get();

            $extra['memberStats'] = [
                'gender' => $details->groupBy('sex')->map->count()->sortDesc(),
                'age' => $details->filter(fn ($d) => $d->date_of_birth)
                    ->groupBy(fn ($d) => (int) floor($d->date_of_birth->age / 10) * 10)
                    ->map->count()->sortKeys()
                    ->mapWithKeys(fn ($v, $k) => [$k.'-'.($k + 9) => $v]),
                'certification' => $details->filter(fn ($d) => $d->certification_level)
                    ->groupBy('certification_level')->map->count()->sortDesc()->take(12),
                'nationality' => $details->filter(fn ($d) => $d->nationality)
                    ->groupBy('nationality')->map->count()->sortDesc()->take(15),
                'language' => $details->filter(fn ($d) => $d->preferred_language)
                    ->groupBy('preferred_language')->map->count()->sortDesc(),
                'total' => $details->count(),
            ];
        }

        // Auto-translate: generate user's locale if missing, and refresh any stale translations
        $locale = app()->getLocale();
        $svc = app(ArticleTranslationService::class);
        try {
            if ($locale !== 'fr' && ! $article->translations->contains('locale', $locale)) {
                $svc->translate($article, $locale);
            }
            foreach ($article->translations->where('stale', true) as $stale) {
                $svc->translate($article, $stale->locale);
            }
            $article->load('translations');
        } catch (\Throwable) {
            // Translation API unavailable — show existing/original
        }

        // Available translation locales for tab UI
        $extra['translatedLocales'] = $article->translations->pluck('locale')->toArray();

        return view('cms.article', compact('article') + $extra);
    }
}
