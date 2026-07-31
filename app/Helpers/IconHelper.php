<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\ThemeSetting;

class IconHelper
{
    protected static ?bool $resolved = null;

    public static function render(string $emoji): string
    {
        return static::enabled() ? $emoji.' ' : '';
    }

    public static function enabled(): bool
    {
        if (static::$resolved !== null) {
            return static::$resolved;
        }

        $user = auth()->user();

        // Authenticated user: use their preference, fall back to admin default
        if ($user) {
            $pref = $user->detail?->show_icons;
            if ($pref !== null) {
                return static::$resolved = (bool) $pref;
            }
        }

        // Admin default for guests (or users without preference)
        return static::$resolved = (bool) ThemeSetting::get('ui_show_icons', '1');
    }

    /** Reset cached value (for testing or after preference change). */
    public static function flush(): void
    {
        static::$resolved = null;
    }
}
