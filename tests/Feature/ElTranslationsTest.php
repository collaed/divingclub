<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ElTranslationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->setLocale('el');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function navChromeStrings(): array
    {
        return [
            'nav About' => ['About', 'Σχετικά'],
            'nav Resources' => ['Resources', 'Πόροι'],
            'nav My Account' => ['My Account', 'Ο λογαριασμός μου'],
            'sidebar Classifieds' => ['Classifieds', 'Μικρές αγγελίες'],
            'sidebar Buddy Requests' => ['Buddy Requests', 'Αιτήματα συντρόφου'],
            'footer Terms' => ['Terms', 'Όροι'],
            'footer Powered by' => ['Powered by', 'Υποστηρίζεται από'],
            'event Edit Layout' => ['Edit Layout', 'Επεξεργασία διάταξης'],
            'home View all' => ['View all', 'Προβολή όλων'],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function eventPageStrings(): array
    {
        return [
            'registration Register another person' => ['Register another person', 'Εγγραφή άλλου ατόμου'],
            'registration show/hide cancelled' => ['show/hide cancelled', 'εμφάνιση/απόκρυψη ακυρωμένων'],
            'participants Instructors' => ['Instructors', 'Εκπαιδευτές'],
            'gear Lend' => ['Lend', 'Δανεισμός'],
            'gear weight(s)' => ['weight(s)', 'βάρος/βάρη'],
            'photos consent' => ["I consent to these photos being shared on the club's social media channels", 'Συναινώ στην κοινοποίηση αυτών των φωτογραφιών στα κανάλια κοινωνικών μέσων του συλλόγου'],
            'payment status heading' => ['Payment Status', 'Κατάσταση πληρωμής'],
            'dive groups heading' => ['Dive Groups', 'Ομάδες κατάδυσης'],
            'open group planner' => ['Open Group Planner', 'Άνοιγμα προγραμματιστή ομάδων'],
        ];
    }

    #[DataProvider('navChromeStrings')]
    public function test_nav_and_chrome_strings_are_translated_to_greek(string $key, string $expected): void
    {
        $this->assertSame($expected, __($key), "'{$key}' should resolve to Greek, not fall back to the source string");
    }

    #[DataProvider('eventPageStrings')]
    public function test_event_page_strings_are_translated_to_greek(string $key, string $expected): void
    {
        $this->assertSame($expected, __($key), "'{$key}' should resolve to Greek, not fall back to the source string");
    }

    public function test_el_json_parses_and_every_fr_session_key_has_a_non_stub_translation(): void
    {
        $el = json_decode((string) file_get_contents(lang_path('el.json')), true, 512, JSON_THROW_ON_ERROR);
        $fr = json_decode((string) file_get_contents(lang_path('fr.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach ([...self::navChromeStrings(), ...self::eventPageStrings()] as $label => [$source]) {
            $this->assertArrayHasKey($source, $el, "missing el.json key for {$label}");
            $this->assertNotSame($source, $el[$source], "'{$source}' is present but left untranslated");
            $this->assertArrayHasKey($source, $fr, "fr.json should also have {$label} — test fixtures are out of sync");
        }
    }
}
