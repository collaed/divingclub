<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class LbTranslationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->setLocale('lb');
    }

    public function test_lb_json_parses_and_core_event_strings_are_not_stubs(): void
    {
        $lb = json_decode((string) file_get_contents(lang_path('lb.json')), true, 512, JSON_THROW_ON_ERROR);
        $fr = json_decode((string) file_get_contents(lang_path('fr.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach (['Edit Layout', 'Register another person', 'show/hide cancelled', 'Instructors', 'Lend', 'weight(s)', 'Payment Status', 'Dive Groups'] as $source) {
            $this->assertArrayHasKey($source, $lb, "missing lb.json key for {$source}");
            $this->assertNotSame($source, $lb[$source], "'{$source}' is present but left untranslated");
            $this->assertArrayHasKey($source, $fr, "fr.json should also have {$source} — test fixtures are out of sync");
        }
    }
}
