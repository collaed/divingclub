<?php

namespace App\Services;

use App\Models\ThemeSetting;
use Illuminate\Support\Facades\Cache;

class ThemeService
{
    public static function css(): string
    {
        $s = Cache::remember('theme_css', 300, fn (): array => ThemeSetting::all_settings());

        $style = $s['ui_style'] ?? 'rounded';
        $radius = match ($style) {
            'sharp' => '0px', 'compact' => '3px', 'classic' => '4px', default => '0.5rem',
        };
        $radiusLg = match ($style) {
            'sharp' => '0px', 'compact' => '4px', 'classic' => '6px', default => '0.75rem',
        };
        $shadow = match ($style) {
            'sharp' => '0 1px 2px rgba(0,0,0,0.1)',
            'compact' => '0 1px 2px rgba(0,0,0,0.06)',
            'classic' => '0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.08)',
            default => '0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06)',
        };
        $shadowHover = match ($style) {
            'sharp' => '0 2px 6px rgba(0,0,0,0.15)',
            'compact' => '0 2px 4px rgba(0,0,0,0.1)',
            'classic' => '0 4px 12px rgba(0,0,0,0.15)',
            default => '0 4px 12px rgba(0,0,0,0.1)',
        };
        $borderWidth = match ($style) {
            'sharp' => '1px', 'classic' => '1px', default => '0px',
        };
        $fontSize = match ($style) {
            'compact' => '0.875rem', default => '1rem',
        };

        return ':root {'
            .'--dc-primary:'.($s['primary_color'] ?? '#003366').';'
            .'--dc-secondary:'.($s['secondary_color'] ?? '#0077be').';'
            .'--dc-accent:'.($s['accent_color'] ?? '#ffc107').';'
            .'--dc-header-start:'.($s['header_gradient_start'] ?? '#001a33').';'
            .'--dc-header-end:'.($s['header_gradient_end'] ?? '#0059a6').';'
            .'--dc-footer-bg:'.($s['footer_bg'] ?? '#1a1a2e').';'
            .'--dc-body-bg:'.($s['body_bg'] ?? '#ffffff').';'
            .'--dc-body-color:'.($s['body_color'] ?? '#333333').';'
            .'--dc-radius:'.$radius.';'
            .'--dc-radius-lg:'.$radiusLg.';'
            .'--dc-shadow:'.$shadow.';'
            .'--dc-shadow-hover:'.$shadowHover.';'
            .'--dc-border-width:'.$borderWidth.';'
            .'--dc-font-size:'.$fontSize.';'
            .'}'
            // Dark mode overrides
            .'[data-bs-theme="dark"]{'
            .'--dc-body-bg:#1a1a2e;'
            .'--dc-body-color:#e0e0e0;'
            .'--dc-footer-bg:#0d0d1a;'
            .'--dc-shadow:0 1px 3px rgba(0,0,0,0.3);'
            .'--dc-shadow-hover:0 4px 12px rgba(0,0,0,0.4);'
            .'}';
    }

    public static function settings(): array
    {
        return Cache::remember('theme_settings', 300, fn (): array => ThemeSetting::all_settings());
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

    public static function stylePresets(): array
    {
        return [
            'rounded' => ['label' => 'Rounded', 'desc' => 'Soft corners, subtle shadows — modern and friendly'],
            'sharp' => ['label' => 'Sharp', 'desc' => 'Square corners, thin borders — clean and precise'],
            'classic' => ['label' => 'Classic', 'desc' => 'Slight rounding, visible borders, higher contrast — traditional admin'],
            'compact' => ['label' => 'Compact', 'desc' => 'Smaller text, tight spacing, minimal rounding — data-dense'],
        ];
    }
}
