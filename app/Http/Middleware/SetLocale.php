<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    protected array $supported = ['en', 'fr', 'de', 'lb', 'pt', 'it', 'nl', 'es', 'pl', 'hu', 'ro'];

    public function handle(Request $request, Closure $next)
    {
        // 1. Authenticated user preference
        if ($request->user()?->detail?->preferred_language && in_array($request->user()->detail->preferred_language, $this->supported)) {
            app()->setLocale($request->user()->detail->preferred_language);
            return $next($request);
        }

        // 2. Session
        if (session('locale') && in_array(session('locale'), $this->supported)) {
            app()->setLocale(session('locale'));
            return $next($request);
        }

        // 3. Browser Accept-Language
        $preferred = $request->getPreferredLanguage($this->supported);
        if ($preferred) {
            app()->setLocale($preferred);
        }

        return $next($request);
    }
}
