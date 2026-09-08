<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureEmailVerified;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\StagingBasicAuth;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

// Ensure storage directories exist (Wasmer Edge deploys from git without them)
foreach ([
    __DIR__.'/../storage/framework/views',
    __DIR__.'/../storage/framework/cache/data',
    __DIR__.'/../storage/framework/sessions',
    __DIR__.'/../storage/logs',
] as $dir) {
    if (! is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'role' => CheckRole::class,
            'verified.email' => EnsureEmailVerified::class,
        ]);
        $middleware->web(prepend: [
            StagingBasicAuth::class,
        ]);
        $middleware->web(append: [
            SetLocale::class,
            EnsureInstalled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // A stale CSRF token (session cookie dropped by the browser — common on
        // iOS Safari with tracking prevention, or a page left open past the
        // session lifetime) otherwise dead-ends the user on the generic 419
        // page. Bounce them back to where they were with a clear message and a
        // fresh token instead.
        $exceptions->render(function (Throwable $e, Request $request): ?RedirectResponse {
            $isCsrf = $e instanceof TokenMismatchException
                || ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 419);

            // JSON clients keep Laravel's default 419 payload.
            if (! $isCsrf || $request->expectsJson()) {
                return null;
            }

            $back = $request->headers->get('referer') ?: url('/');

            return redirect($back)->with('error', __('Your session timed out — please try again.'));
        });
    })->create();
