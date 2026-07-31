<?php

/**
 * Middleware: set application locale from user preference, session, or browser.
 *
 * Reads enabled locales from theme_settings('enabled_locales') which is
 * configured during installation. Falls back to all locales in config/languages.php
 * if no setting exists yet (pre-install or dev mode).
 *
 * @author ClubCEP.eu
 */

namespace App\Http\Middleware;

use App\Helpers\IconHelper;
use App\Helpers\LocaleHelper;
use App\Models\ThemeSetting;
use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    /** Get the list of enabled locale codes. */
    public static function enabledLocales(): array
    {
        return LocaleHelper::enabledLocales();
    }

    /** Get enabled locales with their labels (for the language selector). */
    public static function enabledLocalesWithLabels(): array
    {
        $all = config('languages', []);
        $enabled = static::enabledLocales();

        return collect($all)
            ->only($enabled)
            ->map(fn ($v): mixed => $v['native'] ?? $v['label'])
            ->toArray();
    }

    public function handle(Request $request, Closure $next): mixed
    {
        IconHelper::flush();

        // Apply admin-configured default locale
        $defaultLocale = ThemeSetting::get('default_locale');
        if ($defaultLocale) {
            app()->setLocale($defaultLocale);
            app()->setFallbackLocale($defaultLocale);
        }

        $supported = static::enabledLocales();

        // 1. Authenticated user preference
        $userLang = $request->user()?->detail?->preferred_language;
        if ($userLang && in_array($userLang, $supported)) {
            app()->setLocale($userLang);

            return $next($request);
        }

        // 2. Session
        if (session('locale') && in_array(session('locale'), $supported)) {
            app()->setLocale(session('locale'));

            return $next($request);
        }

        // 3. Browser Accept-Language
        $preferred = $request->getPreferredLanguage($supported);
        if ($preferred) {
            app()->setLocale($preferred);
        }

        return $next($request);
    }
}
