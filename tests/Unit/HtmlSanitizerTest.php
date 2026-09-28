<?php

namespace Tests\Unit;

use App\Helpers\HtmlSanitizer;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    /**
     * The rich editor's alignment buttons write class="text-center" (etc.) on
     * block elements — not inline style, specifically so this survives. Before
     * the 'rich' preset allowed [class] on these tags, this content silently
     * lost its alignment on every save with no error shown anywhere.
     */
    public function test_rich_preset_preserves_bootstrap_classes_on_block_elements(): void
    {
        $html = '<p class="text-center">Centered</p><h2 class="text-end">Right</h2><li class="text-start">Item</li>';

        $clean = HtmlSanitizer::clean($html, 'rich');

        $this->assertStringContainsString('class="text-center"', $clean);
        $this->assertStringContainsString('class="text-end"', $clean);
        $this->assertStringContainsString('class="text-start"', $clean);
    }

    public function test_rich_preset_still_strips_scripts_and_event_handlers(): void
    {
        $html = '<p onclick="alert(1)">Click</p><script>alert(2)</script>';

        $clean = HtmlSanitizer::clean($html, 'rich');

        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('<script>', $clean);
    }

    public function test_basic_preset_is_unaffected_by_the_rich_preset_widening(): void
    {
        // 'basic' (classifieds) must keep its narrower allow-list — no
        // heading tags, no class attribute — unchanged by the 'rich' fix.
        $html = '<p class="text-center">Text</p><h2>Heading</h2>';

        $clean = HtmlSanitizer::clean($html, 'basic');

        $this->assertStringNotContainsString('class=', $clean);
        $this->assertStringNotContainsString('<h2>', $clean);
        $this->assertStringContainsString('<p>Text</p>', $clean);
    }
}
