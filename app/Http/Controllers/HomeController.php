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
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\MemberDetail;
use App\Services\ArticleTranslationService;
use App\Services\ThemeService;
use Illuminate\Support\Facades\Cache;

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

    public function index2()
    {
        $slugs = ['values', 'history', 'bureau', 'member-figures', 'instructors'];
        $sections = Article::whereIn('slug', $slugs)->where('is_published', true)
            ->get()->sortBy(fn ($a) => array_search($a->slug, $slugs))->values();

        $photos = EventPhoto::where('quality_score', '>=', 85)
            ->where('has_faces', false)->inRandomOrder()->limit(6)->pluck('path');

        $events = Event::where('event_date', '>=', now())
            ->orderBy('event_date')->limit(3)->get();

        // Instructor bios for the instructors section
        $instructors = MemberDetail::whereNotNull('instructor_bio')
            ->where('instructor_bio', '!=', '')
            ->where('show_on_public_site', true)
            ->with('user')
            ->get();

        // Bureau members for the bureau section
        $bureauMembers = MemberDetail::where('bureau_member', true)
            ->with('user')
            ->get();

        // Live member stats for the member-figures section
        $memberStats = self::memberStats();

        return view('home2', compact('sections', 'photos', 'events', 'memberStats', 'instructors', 'bureauMembers'))
            ->with('theme', ThemeService::settings());
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
            $extra['memberStats'] = self::memberStats();
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

    public function index3()
    {
        $photos = EventPhoto::randomPublic(8)->pluck('path');
        // One upcoming event per category for variety
        $eventTypes = ['pool', 'dive', 'social', 'trip'];
        $events = collect();
        foreach ($eventTypes as $type) {
            $ev = Event::where('event_date', '>=', now())->where('event_type', $type)->orderBy('event_date')->first();
            if ($ev) {
                $events->push($ev);
            }
        }
        if ($events->count() < 4) {
            // Fill remaining slots with next upcoming of any type not already shown
            $ids = $events->pluck('id');
            Event::where('event_date', '>=', now())->whereNotIn('id', $ids)
                ->orderBy('event_date')->limit(4 - $events->count())->get()
                ->each(fn ($e) => $events->push($e));
        }
        $events = $events->sortBy('event_date')->take(4)->values();
        $stats = self::memberStats();
        $faces = MemberDetail::where(fn ($q) => $q->where('bureau_member', true)->orWhere('active_instructor', true))
            ->where('show_on_public_site', true)
            ->whereHas('user.documents', fn ($q) => $q->where('category', 'medical')->where('is_current', true)->where('expiry_date', '>', now()))
            ->with('user')->get();

        return view('home3', compact('photos', 'events', 'stats', 'faces'))
            ->with('theme', ThemeService::settings());
    }

    /** Cached member statistics used by index2() and member-figures article. */
    private static function memberStats(): array
    {
        return Cache::remember('member_stats', 3600, function () {
            $details = MemberDetail::whereHas('user', fn ($q) => $q->whereNotNull('status_id'))->get();

            return [
                'total' => $details->count(),
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
            ];
        });
    }
}
