<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class EnsureInstalled
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('install*') || $request->is('_debugbar/*') || app()->runningUnitTests()) {
            return $next($request);
        }

        try {
            if (!Schema::hasTable('users') || \App\Models\User::count() === 0) {
                return redirect()->route('install.index');
            }
        } catch (\Exception $e) {
            return redirect()->route('install.index');
        }

        return $next($request);
    }
}
