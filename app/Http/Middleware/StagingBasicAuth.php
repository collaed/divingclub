<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StagingBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.staging_mode')) {
            return $next($request);
        }

        $user = config('app.staging_user', '');
        $pass = config('app.staging_pass', '');

        // Skip basic auth if no credentials configured
        if (empty($user) && empty($pass)) {
            return $next($request);
        }

        if ($request->getUser() !== $user || $request->getPassword() !== $pass) {
            return response('', 401)
                ->header('WWW-Authenticate', 'Basic realm="Staging"');
        }

        return $next($request);
    }
}
