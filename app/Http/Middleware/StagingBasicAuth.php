<?php

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

        $user = config('app.staging_user', 'staging');
        $pass = config('app.staging_pass', 'staging');

        if ($request->getUser() !== $user || $request->getPassword() !== $pass) {
            return response('', 401)
                ->header('WWW-Authenticate', 'Basic realm="Staging"');
        }

        return $next($request);
    }
}
