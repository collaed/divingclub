<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class LoginHistoryController extends Controller
{
    public function index(): View
    {
        $logins = LoginRecord::with('user.detail')
            ->latest()
            ->paginate(50);

        $since = now()->subDay();

        $stats = [
            'logins_24h' => LoginRecord::where('created_at', '>=', $since)->count(),
            'users_24h' => LoginRecord::where('created_at', '>=', $since)->distinct('user_id')->count('user_id'),
        ];

        $failed = DB::table('failed_login_attempts')
            ->where('attempted_at', '>=', $since)
            ->orderByDesc('attempted_at')
            ->limit(30)
            ->get(['email', 'ip_address', 'attempted_at']);

        return view('admin.logins.index', compact('logins', 'stats', 'failed'));
    }
}
