<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class SkTranslationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->setLocale('sk');
    }

    public function test_sk_json_parses_and_core_event_strings_are_not_stubs(): void
    {
        $sk = json_decode((string) file_get_contents(lang_path('sk.json')), true, 512, JSON_THROW_ON_ERROR);
        $fr = json_decode((string) file_get_contents(lang_path('fr.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach (['Edit Layout', 'Register another person', 'show/hide cancelled', 'Instructors', 'Lend', 'weight(s)', 'Payment Status', 'Dive Groups'] as $source) {
            $this->assertArrayHasKey($source, $sk, "missing sk.json key for {$source}");
            $this->assertNotSame($source, $sk[$source], "'{$source}' is present but left untranslated");
            $this->assertArrayHasKey($source, $fr, "fr.json should also have {$source} — test fixtures are out of sync");
        }
    }
}
