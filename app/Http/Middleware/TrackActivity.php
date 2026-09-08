<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\PageVisit;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps `users.last_seen_at` fresh (throttled) for every authenticated
 * request, and — when `tracking.page_visits` is enabled — logs authenticated
 * GET page views to `page_visits` so bureau_master can reconstruct what a
 * member actually did when they report a problem.
 *
 * The work runs in terminate() so it never adds latency to the response.
 */
class TrackActivity
{
    /**
     * Path fragments that are never worth logging as a "page view".
     */
    private const IGNORE_FRAGMENTS = [
        '_debugbar', 'livewire/', 'sw.js', 'build/', 'storage/',
        'favicon', 'robots.txt', '.well-known/', 'admin/logins',
    ];

    /**
     * Minutes between `last_seen_at` writes for the same user.
     */
    private const LAST_SEEN_THROTTLE_MINUTES = 5;

    public function handle(Request $request, Closure $next): mixed
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return;
        }

        // While an admin impersonates a member, requests run as that member —
        // don't attribute their activity to the impersonated user.
        if ($request->hasSession() && $request->session()->has('impersonating')) {
            return;
        }

        // Tracking must never turn a served request into an error. This runs in
        // terminate(), but an uncaught throw here still surfaces as a 500 under
        // php-fpm — and there is a ~10s window during a code-before-migration
        // deploy where last_seen_at / page_visits do not exist yet.
        try {
            $this->touchLastSeen($user);
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            $this->recordVisit($request, $response, $user);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function touchLastSeen(User $user): void
    {
        if ($user->last_seen_at !== null
            && $user->last_seen_at->gt(now()->subMinutes(self::LAST_SEEN_THROTTLE_MINUTES))) {
            return;
        }

        User::withoutTimestamps(fn () => $user->forceFill(['last_seen_at' => now()])->saveQuietly());
    }

    private function recordVisit(Request $request, Response $response, User $user): void
    {
        if (! config('tracking.page_visits')) {
            return;
        }

        if ($request->method() !== 'GET' || $request->ajax() || $request->pjax() || $request->wantsJson()) {
            return;
        }

        $isHtml = str_contains((string) $response->headers->get('Content-Type'), 'text/html');
        if (! $isHtml && $response->getStatusCode() < 400) {
            return;
        }

        $path = '/'.trim($request->path(), '/');
        foreach (self::IGNORE_FRAGMENTS as $fragment) {
            if (str_contains($path, $fragment)) {
                return;
            }
        }

        PageVisit::create([
            'user_id' => $user->getAuthIdentifier(),
            'method' => 'GET',
            'path' => Str::limit($path, 500, ''),
            'route_name' => $request->route()?->getName(),
            'status' => $response->getStatusCode(),
            'ip_address' => $request->ip(),
        ]);
    }
}
