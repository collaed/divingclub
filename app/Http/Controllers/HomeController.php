<?php

declare(strict_types=1);

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

use App\Helpers\SystemContent;
use App\Models\Article;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\ExternalRegistration;
use App\Models\MemberDetail;
use App\Services\ArticleTranslationService;
use App\Services\ThemeService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function index(): RedirectResponse|View
    {
        $user = auth()->user();

        // Public visitors get the visual landing page (an editable, translatable
        // article drives its intro text). Authenticated members get the
        // configurable widget dashboard.
        if (! $user) {
            return $this->landing();
        }

        $layout = HomepageLayoutController::getLayout();
        $widgetTypes = HomepageLayoutController::widgetTypes();

        // Load data for each enabled + visible widget
        $widgets = collect($layout)->map(function (array $w) use ($user): array {
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

        $isAdmin = $user?->can('manage settings');

        return view('home', compact('widgets', 'widgetTypes', 'isAdmin'));
    }

    public function index2(): RedirectResponse|View
    {
        $slugs = ['values', 'history', 'bureau', 'member-figures', 'instructors'];
        $sections = Article::whereIn('slug', $slugs)->where('is_published', true)
            ->get()->sortBy(fn ($a): false|int => array_search($a->slug, $slugs))->values();

        $photos = EventPhoto::where('quality_score', '>=', 85)
            ->where('has_faces', false)->inRandomOrder()->limit(6)->pluck('path');

        $events = Event::where('event_date', '>=', now())
            ->orderBy('event_date')->limit(3)->get();

        // Instructor list for the instructors section
        $instructors = MemberDetail::where('active_instructor', true)
            ->where('show_on_public_site', true)
            ->with('user')
            ->orderBy('last_name')
            ->get();

        // Bureau members for the bureau section
        /** @phpstan-ignore-next-line */
        $bureauMembers = MemberDetail::whereHas('user', fn (Builder $q) => $q->role(['bureau_master', 'bureau_technical', 'bureau_finance']))
            ->with('user')
            ->get();

        // Live member stats for the member-figures section
        $memberStats = $this->memberStats();

        return view('home2', compact('sections', 'photos', 'events', 'memberStats', 'instructors', 'bureauMembers'))
            ->with('theme', ThemeService::settings());
    }

    public function showArticle(string $slug): RedirectResponse|View
    {
        $article = Article::where('slug', $slug)->active()->with('translations')->firstOrFail();

        if (! $article->is_public && ! auth()->check()) {
            return redirect()->route('login');
        }

        $extra = [];

        // Dynamic instructor list for the instructors page
        if ($slug === 'instructors') {
            $extra['instructors'] = MemberDetail::where('active_instructor', true)
                ->where('show_on_public_site', true)
                ->with('user')
                ->orderBy('last_name')
                ->get();
        }

        // Live member statistics charts
        if ($slug === 'member-figures') {
            $extra['memberStats'] = $this->memberStats();
        }

        // Auto-translate: generate user's locale if missing, and refresh any stale translations
        $locale = app()->getLocale();
        $svc = app(ArticleTranslationService::class);
        try {
            if ($locale !== 'fr' && ! $article->translations->contains('locale', $locale)) {
                $svc->translate($article, $locale);
            }
            $article->load('translations');
        } catch (\Throwable) {
            // Translation API unavailable — show existing/original
        }

        // Available translation locales for tab UI
        $extra['translatedLocales'] = $article->translations->pluck('locale')->toArray();

        return view('cms.article', compact('article') + $extra);
    }

    public function index3(): RedirectResponse|View
    {
        return $this->landing();
    }

    /**
     * The public visual landing page (home3). Its intro/hero text is an
     * editable, translatable system article (SystemContent::HOME_LANDING) so the
     * bureau can maintain it through the normal article CMS in every language.
     */
    private function landing(): View
    {
        $photos = EventPhoto::randomPublic(8)->pluck('path');
        // One upcoming event per distinct activity title for variety
        $events = Event::where('event_date', '>=', now())
            ->orderBy('event_date')->limit(50)->get()
            ->unique(fn ($e) => mb_strtolower($e->title))
            ->take(4)->values();
        $stats = $this->memberStats();
        /** @phpstan-ignore-next-line */
        $faces = MemberDetail::whereHas('user', fn (Builder $q) => $q->role(['bureau_master', 'bureau_technical', 'bureau_finance', 'instructor']))
            ->whereHas('user.documents', fn ($q) => $q->where('category', 'medical')->where('is_current', true)->where('expiry_date', '>', now()))
            ->with('user')->get()->unique('user_id');

        $landingArticle = SystemContent::article(SystemContent::HOME_LANDING);
        $landingTitle = SystemContent::title(SystemContent::HOME_LANDING);
        $landingBody = SystemContent::body(SystemContent::HOME_LANDING);

        return view('home3', compact('photos', 'events', 'stats', 'faces', 'landingArticle', 'landingTitle', 'landingBody'))
            ->with('theme', ThemeService::settings());
    }

    public function index4(): RedirectResponse|View
    {
        $user = auth()->user();
        if (! $user) {
            return redirect()->route('home3');
        }

        $isBureau = $user->isBureau();
        $isInstructor = $user->hasRole('instructor');

        $myRegs = $user->eventRegistrations()->where('status', 'registered')
            ->whereHas('event', fn ($q) => $q->where('event_date', '>=', now()))
            ->with('event')->limit(3)->get()->pluck('event');
        $nextEvents = Event::where('event_date', '>=', now())->orderBy('event_date')->limit(3)->get();
        $articles = Article::where('is_published', true)->latest()->limit(2)->get();

        $worklist = $isBureau ? [
            'certs' => Document::where('category', 'medical')->where('is_verified', false)->count(),
            'ext_regs' => ExternalRegistration::where('status', 'pending')->count(),
        ] : [];

        return view('home4', compact('user', 'isBureau', 'isInstructor', 'myRegs', 'nextEvents', 'articles', 'worklist'))
            ->with('theme', ThemeService::settings());
    }

    /** Cached member statistics used by index2() and member-figures article. */
    private function memberStats(): array
    {
        return Cache::remember('member_stats', 3600, function (): array {
            // Only count active members (paid current season or event in last 18mo)
            $currentYear = (string) (now()->month >= 9 ? now()->year + 1 : now()->year);
            $cutoff = now()->subMonths(18)->format('Y-m-d');
            $details = MemberDetail::whereHas('user', fn ($q) => $q->whereNotNull('status_id')->where(fn ($u) => $u->whereJsonContains('cotisation_years', $currentYear)->orWhereHas('eventRegistrations', fn ($r) => $r->where('status', 'confirmed')->whereHas('event', fn ($e) => $e->where('event_date', '>=', $cutoff)))))->get();

            return [
                'total' => $details->count(),
                'gender' => $details->groupBy('sex')->map->count()->sortDesc(),
                'age' => $details->filter(fn (MemberDetail $d) => $d->date_of_birth)
                    ->groupBy(fn (MemberDetail $d): int => (int) floor($d->date_of_birth->age / 10) * 10)
                    ->map->count()->sortKeys()
                    ->mapWithKeys(fn ($v, $k): array => [$k.'-'.($k + 9) => $v]),
                'certification' => $details->filter(fn (MemberDetail $d) => $d->certification_level)
                    ->groupBy('certification_level')->map->count()->sortDesc()->take(12),
                'nationality' => $details->filter(fn (MemberDetail $d) => $d->nationality)
                    ->groupBy('nationality')->map->count()->sortDesc()->take(15),
                'language' => $details->filter(fn (MemberDetail $d) => $d->preferred_language)
                    ->groupBy('preferred_language')->map->count()->sortDesc(),
            ];
        });
    }
}
