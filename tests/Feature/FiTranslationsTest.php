<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class FiTranslationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->setLocale('fi');
    }

    public function test_fi_json_parses_and_core_event_strings_are_not_stubs(): void
    {
        $fi = json_decode((string) file_get_contents(lang_path('fi.json')), true, 512, JSON_THROW_ON_ERROR);
        $fr = json_decode((string) file_get_contents(lang_path('fr.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach (['Edit Layout', 'Register another person', 'show/hide cancelled', 'Instructors', 'Lend', 'weight(s)', 'Payment Status', 'Dive Groups'] as $source) {
            $this->assertArrayHasKey($source, $fi, "missing fi.json key for {$source}");
            $this->assertNotSame($source, $fi[$source], "'{$source}' is present but left untranslated");
            $this->assertArrayHasKey($source, $fr, "fr.json should also have {$source} — test fixtures are out of sync");
        }
    }

    /**
     * Regression guard: fi.json's original 674-key base was found badly corrupted
     * this session — the same bug as et.json (values shifted onto the wrong key,
     * leaked HTML fragments). Repaired in full; this locks a representative sample
     * in place.
     */
    public function test_previously_corrupted_base_keys_now_hold_their_own_correct_translation(): void
    {
        $expected = [
            'Save' => 'Tallenna',
            'Cancel' => 'Peruuta',
            'Delete' => 'Poista',
            'Edit' => 'Muokkaa',
            'Login' => 'Kirjautuminen',
            'Logout' => 'Uloskirjautuminen',
            'Profile' => 'Profiili',
            'Password' => 'Salasana',
            'Remember me' => 'Muista minut',
            'Welcome' => 'Tervetuloa',
            'Clear' => 'Tyhjennä',
        ];

        foreach ($expected as $key => $value) {
            $this->assertSame($value, __($key), "'{$key}' resolved to an unexpected value — has fi.json regressed?");
        }

        $this->assertNotSame(__('Clear'), __('Welcome'));
    }

    /** No leaked HTML fragments anywhere in the file (the corruption's clearest fingerprint). */
    public function test_fi_json_has_no_leaked_html_fragments(): void
    {
        $fi = json_decode((string) file_get_contents(lang_path('fi.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach ($fi as $key => $value) {
            if (! is_string($value)) {
                continue;
            }
            $this->assertDoesNotMatchRegularExpression('/<[a-z]|class=|\/>/i', $value, "fi.json key '{$key}' contains a leaked HTML fragment");
        }
    }
}
