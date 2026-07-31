<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\ThemeSetting;

/**
 * Centralized locale utilities — used by middleware, jobs, and views.
 */
class LocaleHelper
{
    /** Get the list of enabled locale codes. */
    public static function enabledLocales(): array
    {
        $stored = ThemeSetting::get('enabled_locales');
        if ($stored) {
            $decoded = json_decode($stored, true);
            if (is_array($decoded) && $decoded !== []) {
                return $decoded;
            }
        }

        return array_keys(config('languages', ['en' => []]));
    }

    /** Get enabled locales with their labels (for the language selector). */
    public static function enabledLocalesWithLabels(): array
    {
        $all = config('languages', []);
        $enabled = static::enabledLocales();

        return array_intersect_key($all, array_flip($enabled));
    }
}
