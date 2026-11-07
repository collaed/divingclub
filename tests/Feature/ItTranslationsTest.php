<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ItTranslationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->setLocale('it');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function navChromeStrings(): array
    {
        return [
            'nav About' => ['About', 'Chi siamo'],
            'nav Resources' => ['Resources', 'Risorse'],
            'sidebar Classifieds' => ['Classifieds', 'Annunci'],
            'sidebar Buddy Requests' => ['Buddy Requests', 'Richieste di compagno'],
            'footer Terms' => ['Terms', 'Termini'],
            'event Edit Layout' => ['Edit Layout', 'Modifica layout'],
        ];
    }

    #[DataProvider('navChromeStrings')]
    public function test_nav_and_chrome_strings_are_translated_to_italian(string $key, string $expected): void
    {
        $this->assertSame($expected, __($key), "'{$key}' should resolve to Italian, not fall back to the source string");
    }

    public function test_it_json_parses_and_core_event_strings_are_not_stubs(): void
    {
        $it = json_decode((string) file_get_contents(lang_path('it.json')), true, 512, JSON_THROW_ON_ERROR);
        $fr = json_decode((string) file_get_contents(lang_path('fr.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach (['Register another person', 'show/hide cancelled', 'Instructors', 'Lend', 'weight(s)', 'Payment Status', 'Dive Groups'] as $source) {
            $this->assertArrayHasKey($source, $it, "missing it.json key for {$source}");
            $this->assertNotSame($source, $it[$source], "'{$source}' is present but left untranslated");
            $this->assertArrayHasKey($source, $fr, "fr.json should also have {$source} — test fixtures are out of sync");
        }
    }
}
