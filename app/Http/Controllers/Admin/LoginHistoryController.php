<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Event;
use App\Models\LoginRecord;
use App\Models\PageVisit;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LoginHistoryController extends Controller
{
    /** Static labels for routes whose title isn't a database record. */
    private function routeLabels(): array
    {
        return [
            'home' => __('Home'),
            'events.index' => __('Events list'),
            'members.directory' => __('Member directory'),
            'members.trombinoscope' => __('Photo directory'),
            'dues.show' => __('Dues calculator'),
            'profile.show' => __('My profile'),
        ];
    }

    public function index(Request $request): View
    {
        $since = now()->subDay();

        $logins = LoginRecord::with('user.detail')
            ->latest()
            ->paginate(50);

        $stats = [
            'logins_24h' => LoginRecord::where('created_at', '>=', $since)->count(),
            'users_24h' => LoginRecord::where('created_at', '>=', $since)->distinct('user_id')->count('user_id'),
        ];

        $failed = DB::table('failed_login_attempts')
            ->where('attempted_at', '>=', $since)
            ->orderByDesc('attempted_at')
            ->limit(30)
            ->get(['email', 'ip_address', 'attempted_at']);

        $members = User::query()
            ->with('detail')
            ->withMax('loginRecords as last_login_at', 'created_at')
            ->whereNotNull('last_seen_at')
            ->orderByDesc('last_seen_at')
            ->limit(300)
            ->get(['id', 'username', 'primary_email', 'last_seen_at']);

        $neverSeen = User::whereNull('last_seen_at')->count();

        $activityEnabled = (bool) config('tracking.page_visits');
        $retentionDays = (int) config('tracking.retention_days', 3);
        $activitySince = now()->subDays($retentionDays);

        $connections = PageVisit::query()
            ->where('created_at', '>=', $activitySince)
            ->selectRaw('user_id, MAX(created_at) as last_at, MIN(created_at) as first_at, COUNT(*) as hits')
            ->groupBy('user_id')
            ->orderByRaw('MAX(created_at) DESC')
            ->limit(50)
            ->get();

        $connectionUsers = User::with('detail')
            ->whereIn('id', $connections->pluck('user_id')->filter()->all())
            ->get()
            ->keyBy('id');

        // "Trail" covers two reverse-direction lookups with the same query
        // shape: one member's whole history (?user=), or — to answer "who
        // browsed a page matching this?" — every visit whose path matches a
        // typed pattern (?path=), regardless of who made it. Either, both, or
        // neither can be set; neither falls back to the connections summary.
        $pathSearch = trim((string) $request->get('path', ''));
        $trailUser = null;
        $trail = null;
        $trailTitles = [];
        $trailUsers = [];
        if ($request->integer('user') > 0 || $pathSearch !== '') {
            if ($request->integer('user') > 0) {
                $trailUser = User::with('detail')->find($request->integer('user'));
            }

            $like = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';
            $trail = PageVisit::query()
                ->where('created_at', '>=', $activitySince)
                ->when($trailUser, fn ($q) => $q->where('user_id', $trailUser->id))
                ->when($pathSearch !== '', fn ($q) => $q->where('path', $like, "%{$pathSearch}%"))
                ->orderByDesc('created_at')
                ->limit(500)
                ->get();
            $trailTitles = $this->resolvePageTitles($trail);

            // Only fetched when the result can span more than one member —
            // the single-user trail already knows who it's for via $trailUser.
            if (! $trailUser) {
                $trailUsers = User::with('detail')
                    ->whereIn('id', $trail->pluck('user_id')->filter()->unique()->all())
                    ->get()->keyBy('id');
            }
        }

        return view('admin.logins.index', compact(
            'logins', 'stats', 'failed',
            'members', 'neverSeen',
            'activityEnabled', 'retentionDays',
            'connections', 'connectionUsers', 'trail', 'trailUser', 'trailTitles', 'trailUsers', 'pathSearch',
        ));
    }

    /**
     * Resolves each visit's page/event title in as few queries as possible:
     * one batch lookup per record-backed route, plus a static label map for
     * the rest. Keyed by PageVisit id.
     *
     * @return array<int, string|null>
     */
    private function resolvePageTitles(Collection $visits): array
    {
        $idFromPath = fn (PageVisit $v): int => (int) basename(rtrim($v->path, '/'));

        $eventIds = $visits->where('route_name', 'events.show')->map($idFromPath)->filter()->unique();
        $events = Event::whereIn('id', $eventIds)->pluck('title', 'id');

        $articleSlugs = $visits->where('route_name', 'article.show')->map(fn (PageVisit $v): string => basename(rtrim($v->path, '/')))->unique();
        $articles = Article::whereIn('slug', $articleSlugs)->pluck('title', 'slug');

        $labels = $this->routeLabels();

        return $visits->mapWithKeys(function (PageVisit $v) use ($idFromPath, $events, $articles, $labels): array {
            $title = match ($v->route_name) {
                'events.show' => $events[$idFromPath($v)] ?? null,
                'article.show' => $articles[basename(rtrim($v->path, '/'))] ?? null,
                default => $labels[$v->route_name] ?? null,
            };

            return [$v->id => $title];
        })->all();
    }
}
