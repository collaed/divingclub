<?php

namespace Tests\Unit;

use App\Services\ThemeService;
use PHPUnit\Framework\TestCase;

/**
 * @group p2
 */
class ThemeServiceTest extends TestCase
{
    public function test_presets_returns_six_themes(): void
    {
        $presets = ThemeService::presets();

        $this->assertCount(6, $presets);
        $this->assertArrayHasKey('ocean', $presets);
        $this->assertArrayHasKey('coral', $presets);
        $this->assertArrayHasKey('lagoon', $presets);
        $this->assertArrayHasKey('abyss', $presets);
        $this->assertArrayHasKey('tropical', $presets);
        $this->assertArrayHasKey('arctic', $presets);
    }

    public function test_each_preset_has_required_color_keys(): void
    {
        $required = ['primary_color', 'secondary_color', 'accent_color', 'header_gradient_start', 'header_gradient_end', 'footer_bg'];

        foreach (ThemeService::presets() as $name => $preset) {
            foreach ($required as $key) {
                $this->assertArrayHasKey($key, $preset, "Preset '{$name}' missing key '{$key}'");
            }
        }
    }

    public function test_all_preset_colors_are_valid_hex(): void
    {
        foreach (ThemeService::presets() as $name => $preset) {
            foreach ($preset as $key => $value) {
                $this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', $value, "Preset '{$name}' key '{$key}' is not valid hex: {$value}");
            }
        }
    }

    public function test_style_presets_returns_four_styles(): void
    {
        $styles = ThemeService::stylePresets();

        $this->assertCount(4, $styles);
        $this->assertArrayHasKey('rounded', $styles);
        $this->assertArrayHasKey('sharp', $styles);
        $this->assertArrayHasKey('classic', $styles);
        $this->assertArrayHasKey('compact', $styles);
    }

    public function test_style_presets_have_label_and_desc(): void
    {
        foreach (ThemeService::stylePresets() as $name => $style) {
            $this->assertArrayHasKey('label', $style, "Style '{$name}' missing 'label'");
            $this->assertArrayHasKey('desc', $style, "Style '{$name}' missing 'desc'");
        }
    }
}
