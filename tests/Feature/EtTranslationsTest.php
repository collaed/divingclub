<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EtTranslationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->setLocale('et');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function navChromeStrings(): array
    {
        return [
            'nav About' => ['About', 'Meist'],
            'nav Resources' => ['Resources', 'Ressursid'],
            'nav My Account' => ['My Account', 'Minu konto'],
            'sidebar Classifieds' => ['Classifieds', 'Kuulutused'],
            'sidebar Buddy Requests' => ['Buddy Requests', 'Sukeldumispartneri soovid'],
            'footer Terms' => ['Terms', 'Tingimused'],
            'footer Powered by' => ['Powered by', 'Töötab platvormil'],
            'event Edit Layout' => ['Edit Layout', 'Muuda paigutust'],
            'home View all' => ['View all', 'Vaata kõiki'],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function eventPageStrings(): array
    {
        return [
            'registration Register another person' => ['Register another person', 'Registreeri teine inimene'],
            'registration show/hide cancelled' => ['show/hide cancelled', 'näita/peida tühistatud'],
            'participants Instructors' => ['Instructors', 'Instruktorid'],
            'gear Lend' => ['Lend', 'Laena'],
            'gear weight(s)' => ['weight(s)', 'raskus(ed)'],
            'photos consent' => ["I consent to these photos being shared on the club's social media channels", 'Nõustun, et neid fotosid jagatakse klubi sotsiaalmeedia kanalites'],
            'payment status heading' => ['Payment Status', 'Makse olek'],
            'dive groups heading' => ['Dive Groups', 'Sukeldumisrühmad'],
            'open group planner' => ['Open Group Planner', 'Ava rühmaplaneerija'],
        ];
    }

    /**
     * Regression guard: et.json's original 674-key base was found badly corrupted
     * this session — leaked HTML fragments, and values shifted onto the wrong keys
     * (e.g. "Remember me" held "Did you forget your password?", "Logout" held
     * "Dashboard"). Repaired in full; this locks a representative sample in place.
     */
    public function test_previously_corrupted_base_keys_now_hold_their_own_correct_translation(): void
    {
        $expected = [
            'Save' => 'Salvesta',
            'Cancel' => 'Tühista',
            'Delete' => 'Kustuta',
            'Edit' => 'Muuda',
            'Login' => 'Sisselogimine',
            'Logout' => 'Väljalogimine',
            'Profile' => 'Profiil',
            'Password' => 'Parool',
            'Remember me' => 'Jäta mind meelde',
            'Welcome' => 'Tere tulemast',
            'Clear' => 'Tühjenda',
            'Full Name' => 'Täisnimi',
            'Registration' => 'Registreerumine',
        ];

        foreach ($expected as $key => $value) {
            $this->assertSame($value, __($key), "'{$key}' resolved to an unexpected value — has et.json regressed?");
        }

        // No two of these distinct keys should still collide on one borrowed value.
        $this->assertNotSame(__('Clear'), __('Welcome'));
    }

    /** No leaked HTML fragments anywhere in the file (the corruption's clearest fingerprint). */
    public function test_et_json_has_no_leaked_html_fragments(): void
    {
        $et = json_decode((string) file_get_contents(lang_path('et.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach ($et as $key => $value) {
            if (! is_string($value)) {
                continue;
            }
            $this->assertDoesNotMatchRegularExpression('/<[a-z]|class=|\/>/i', $value, "et.json key '{$key}' contains a leaked HTML fragment");
        }
    }

    #[DataProvider('navChromeStrings')]
    public function test_nav_and_chrome_strings_are_translated_to_estonian(string $key, string $expected): void
    {
        $this->assertSame($expected, __($key), "'{$key}' should resolve to Estonian, not fall back to the source string");
    }

    #[DataProvider('eventPageStrings')]
    public function test_event_page_strings_are_translated_to_estonian(string $key, string $expected): void
    {
        $this->assertSame($expected, __($key), "'{$key}' should resolve to Estonian, not fall back to the source string");
    }

    public function test_et_json_parses_and_every_fr_session_key_has_a_non_stub_translation(): void
    {
        $et = json_decode((string) file_get_contents(lang_path('et.json')), true, 512, JSON_THROW_ON_ERROR);
        $fr = json_decode((string) file_get_contents(lang_path('fr.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach ([...self::navChromeStrings(), ...self::eventPageStrings()] as $label => [$source]) {
            $this->assertArrayHasKey($source, $et, "missing et.json key for {$label}");
            $this->assertNotSame($source, $et[$source], "'{$source}' is present but left untranslated");
            $this->assertArrayHasKey($source, $fr, "fr.json should also have {$label} — test fixtures are out of sync");
        }
    }
}
