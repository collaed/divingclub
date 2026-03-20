<?php

/**
 * Available languages configuration.
 *
 * This file defines all languages the application can support.
 * During installation, the admin selects which languages to enable.
 * The enabled subset is stored in theme_settings('enabled_locales')
 * as a JSON array. The middleware and language selector read from there.
 *
 * @author ClubCEP.eu
 */

return [
    'en' => ['label' => 'English',        'flag' => '🇬🇧', 'native' => 'English'],
    'fr' => ['label' => 'Français',       'flag' => '🇫🇷', 'native' => 'Français'],
    'de' => ['label' => 'Deutsch',        'flag' => '🇩🇪', 'native' => 'Deutsch'],
    'lb' => ['label' => 'Luxembourgish',  'flag' => '🇱🇺', 'native' => 'Lëtzebuergesch'],
    'pt' => ['label' => 'Portuguese',     'flag' => '🇵🇹', 'native' => 'Português'],
    'it' => ['label' => 'Italian',        'flag' => '🇮🇹', 'native' => 'Italiano'],
    'nl' => ['label' => 'Dutch',          'flag' => '🇳🇱', 'native' => 'Nederlands'],
    'es' => ['label' => 'Spanish',        'flag' => '🇪🇸', 'native' => 'Español'],
    'pl' => ['label' => 'Polish',         'flag' => '🇵🇱', 'native' => 'Polski'],
    'hu' => ['label' => 'Hungarian',      'flag' => '🇭🇺', 'native' => 'Magyar'],
    'ro' => ['label' => 'Romanian',       'flag' => '🇷🇴', 'native' => 'Română'],
];
