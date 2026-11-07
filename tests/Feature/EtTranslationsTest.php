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
