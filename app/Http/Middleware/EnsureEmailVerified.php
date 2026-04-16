<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (auth()->check() && ! auth()->user()->email_verified_at) {
            return redirect()->route('verification.notice');
        }

        // Force password change on first login
        if (auth()->check() && auth()->user()->must_change_password) {
            if (! $request->routeIs('profile.show', 'profile.update.password', 'logout')) {
                return redirect()->route('profile.show', ['tab' => 'private'])
                    ->with('warning', __('Please change your password before continuing.'));
            }
        }

        return $next($request);
    }
}
