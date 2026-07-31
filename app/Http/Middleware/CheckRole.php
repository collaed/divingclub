<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        if (! auth()->check() || ! auth()->user()->hasAnyRole($roles)) {
            abort(403);
        }

        return $next($request);
    }
}
