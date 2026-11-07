<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class NlTranslationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->setLocale('nl');
    }

    public function test_nl_json_parses_and_core_event_strings_are_not_stubs(): void
    {
        $nl = json_decode((string) file_get_contents(lang_path('nl.json')), true, 512, JSON_THROW_ON_ERROR);
        $fr = json_decode((string) file_get_contents(lang_path('fr.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach (['Edit Layout', 'Register another person', 'show/hide cancelled', 'Instructors', 'Lend', 'weight(s)', 'Payment Status', 'Dive Groups'] as $source) {
            $this->assertArrayHasKey($source, $nl, "missing nl.json key for {$source}");
            $this->assertNotSame($source, $nl[$source], "'{$source}' is present but left untranslated");
            $this->assertArrayHasKey($source, $fr, "fr.json should also have {$source} — test fixtures are out of sync");
        }
    }
}
