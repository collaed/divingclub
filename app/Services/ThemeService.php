<?php

namespace App\Services;

use App\Models\ThemeSetting;
use Illuminate\Support\Facades\Cache;

class ThemeService
{
    public static function css(): string
    {
        $s = Cache::remember('theme_css', 300, fn () => ThemeSetting::all_settings());

        return ':root {'
            . '--dc-primary:' . ($s['primary_color'] ?? '#003366') . ';'
            . '--dc-secondary:' . ($s['secondary_color'] ?? '#0077be') . ';'
            . '--dc-accent:' . ($s['accent_color'] ?? '#ffc107') . ';'
            . '--dc-header-start:' . ($s['header_gradient_start'] ?? '#001a33') . ';'
            . '--dc-header-end:' . ($s['header_gradient_end'] ?? '#0059a6') . ';'
            . '--dc-footer-bg:' . ($s['footer_bg'] ?? '#1a1a2e') . ';'
            . '--dc-body-bg:' . ($s['body_bg'] ?? '#ffffff') . ';'
            . '--dc-body-color:' . ($s['body_color'] ?? '#333333') . ';'
            . '}';
    }

    public static function settings(): array
    {
        return Cache::remember('theme_settings', 300, fn () => ThemeSetting::all_settings());
    }

    public static function presets(): array
    {
        return [
            'ocean' => ['primary_color' => '#003366', 'secondary_color' => '#0077be', 'accent_color' => '#ffc107', 'header_gradient_start' => '#001a33', 'header_gradient_end' => '#0059a6', 'footer_bg' => '#1a1a2e'],
            'coral' => ['primary_color' => '#c0392b', 'secondary_color' => '#e74c3c', 'accent_color' => '#f39c12', 'header_gradient_start' => '#7b241c', 'header_gradient_end' => '#e74c3c', 'footer_bg' => '#2c3e50'],
            'lagoon' => ['primary_color' => '#00695c', 'secondary_color' => '#26a69a', 'accent_color' => '#ffab40', 'header_gradient_start' => '#004d40', 'header_gradient_end' => '#00897b', 'footer_bg' => '#263238'],
            'abyss' => ['primary_color' => '#1a237e', 'secondary_color' => '#3949ab', 'accent_color' => '#00e5ff', 'header_gradient_start' => '#0d1642', 'header_gradient_end' => '#283593', 'footer_bg' => '#0a0a1a'],
            'tropical' => ['primary_color' => '#00838f', 'secondary_color' => '#4dd0e1', 'accent_color' => '#ff6f00', 'header_gradient_start' => '#006064', 'header_gradient_end' => '#0097a7', 'footer_bg' => '#1b2631'],
            'arctic' => ['primary_color' => '#37474f', 'secondary_color' => '#78909c', 'accent_color' => '#80deea', 'header_gradient_start' => '#263238', 'header_gradient_end' => '#546e7a', 'footer_bg' => '#1c2833'],
        ];
    }
}
