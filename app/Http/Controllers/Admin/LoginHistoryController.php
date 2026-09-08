<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginRecord;
use App\Models\PageVisit;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoginHistoryController extends Controller
{
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

        $trailUser = null;
        $trail = null;
        if ($request->integer('user') > 0) {
            $trailUser = User::with('detail')->find($request->integer('user'));
            $trail = PageVisit::query()
                ->where('user_id', $request->integer('user'))
                ->where('created_at', '>=', $activitySince)
                ->orderByDesc('created_at')
                ->limit(500)
                ->get();
        }

        return view('admin.logins.index', compact(
            'logins', 'stats', 'failed',
            'members', 'neverSeen',
            'activityEnabled', 'retentionDays',
            'connections', 'connectionUsers', 'trail', 'trailUser',
        ));
    }
}
