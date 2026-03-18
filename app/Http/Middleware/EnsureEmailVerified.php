<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (auth()->check() && !auth()->user()->email_verified_at) {
            return redirect()->route('verification.notice');
        }
        return $next($request);
    }
}
