<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeTranslationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->setLocale('de');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function navChromeStrings(): array
    {
        return [
            'nav About' => ['About', 'Über uns'],
            'nav Resources' => ['Resources', 'Ressourcen'],
            'nav My Account' => ['My Account', 'Mein Konto'],
            'sidebar Classifieds' => ['Classifieds', 'Kleinanzeigen'],
            'sidebar Buddy Requests' => ['Buddy Requests', 'Buddy-Anfragen'],
            'footer Terms' => ['Terms', 'Bedingungen'],
            'footer Powered by' => ['Powered by', 'Bereitgestellt von'],
            'event Edit Layout' => ['Edit Layout', 'Layout bearbeiten'],
            'home View all' => ['View all', 'Alle anzeigen'],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function eventPageStrings(): array
    {
        return [
            'registration Register another person' => ['Register another person', 'Eine weitere Person anmelden'],
            'registration show/hide cancelled' => ['show/hide cancelled', 'Stornierte anzeigen/ausblenden'],
            'participants Instructors' => ['Instructors', 'Tauchlehrer'],
            'gear Lend' => ['Lend', 'Verleihen'],
            'gear weight(s)' => ['weight(s)', 'Gewicht(e)'],
            'payment balance explainer' => ['Positive = you owe the club. Negative = the club owes you.', 'Positiv = Sie schulden dem Verein. Negativ = der Verein schuldet Ihnen.'],
            'photos consent' => ["I consent to these photos being shared on the club's social media channels", 'Ich stimme zu, dass diese Fotos in den sozialen Netzwerken des Vereins geteilt werden'],
        ];
    }

    #[DataProvider('navChromeStrings')]
    public function test_nav_and_chrome_strings_are_translated_to_german(string $key, string $expected): void
    {
        $this->assertSame($expected, __($key), "'{$key}' should resolve to German, not fall back to the source string");
    }

    #[DataProvider('eventPageStrings')]
    public function test_event_page_strings_are_translated_to_german(string $key, string $expected): void
    {
        $this->assertSame($expected, __($key), "'{$key}' should resolve to German, not fall back to the source string");
    }

    public function test_de_json_parses_and_every_fr_session_key_has_a_non_stub_translation(): void
    {
        $de = json_decode((string) file_get_contents(lang_path('de.json')), true, 512, JSON_THROW_ON_ERROR);
        $fr = json_decode((string) file_get_contents(lang_path('fr.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach ([...self::navChromeStrings(), ...self::eventPageStrings()] as $label => [$source]) {
            $this->assertArrayHasKey($source, $de, "missing de.json key for {$label}");
            $this->assertNotSame($source, $de[$source], "'{$source}' is present but left untranslated");
            $this->assertArrayHasKey($source, $fr, "fr.json should also have {$label} — test fixtures are out of sync");
        }
    }
}
