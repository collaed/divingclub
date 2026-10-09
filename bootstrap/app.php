<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureEmailVerified;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\StagingBasicAuth;
use App\Http\Middleware\TrackActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        // Set client-side by the landing page's JS, so it must not be encrypted.
        $middleware->encryptCookies(except: ['cep_seen_landing']);
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
            TrackActivity::class,
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

            // A request with no session cookie at all (vs. one carrying a stale
            // token) points at the browser dropping it mid-visit — the iOS Safari
            // pattern seen in ac64b76 — rather than someone just sitting on a page
            // past the session lifetime. Logged so a recurrence shows up here
            // instead of needing a raw access-log search to confirm.
            Log::warning('CSRF/session mismatch bounced back to a fresh page', [
                'path' => $request->path(),
                'method' => $request->method(),
                'had_session_cookie' => $request->hasCookie(config('session.cookie')),
                'user_agent' => $request->userAgent(),
            ]);

            $back = $request->headers->get('referer') ?: url('/');

            return redirect($back)->with('error', __('Your session timed out — please try again.'));
        });
    })->create();
