<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class EnsureInstalled
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->is('install*') || $request->is('_debugbar/*') || app()->runningUnitTests()) {
            return $next($request);
        }

        $installed = Cache::remember('app_installed', 3600, function (): bool {
            try {
                return Schema::hasTable('users') && User::count() > 0;
            } catch (\Exception) {
                return false;
            }
        });

        if (! $installed) {
            Cache::forget('app_installed');

            return redirect()->route('install.index');
        }

        return $next($request);
    }
}
