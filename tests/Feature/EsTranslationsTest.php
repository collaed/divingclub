<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EsTranslationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->setLocale('es');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function navChromeStrings(): array
    {
        return [
            'nav About' => ['About', 'Acerca de'],
            'nav Resources' => ['Resources', 'Recursos'],
            'nav My Account' => ['My Account', 'Mi cuenta'],
            'sidebar Classifieds' => ['Classifieds', 'Anuncios clasificados'],
            'sidebar Buddy Requests' => ['Buddy Requests', 'Solicitudes de compañero'],
            'footer Terms' => ['Terms', 'Condiciones'],
            'footer Powered by' => ['Powered by', 'Desarrollado con'],
            'event Edit Layout' => ['Edit Layout', 'Editar diseño'],
            'home View all' => ['View all', 'Ver todo'],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function eventPageStrings(): array
    {
        return [
            'registration Register another person' => ['Register another person', 'Inscribir a otra persona'],
            'registration show/hide cancelled' => ['show/hide cancelled', 'mostrar/ocultar cancelados'],
            'participants Instructors' => ['Instructors', 'Instructores'],
            'gear Lend' => ['Lend', 'Prestar'],
            'gear weight(s)' => ['weight(s)', 'peso(s)'],
            'payment balance explainer' => ['Positive = you owe the club. Negative = the club owes you.', 'Positivo = usted debe al club. Negativo = el club le debe a usted.'],
            'photos consent' => ["I consent to these photos being shared on the club's social media channels", 'Consiento que estas fotos se compartan en las redes sociales del club'],
            'payment status heading' => ['Payment Status', 'Estado de pago'],
            'dive groups heading' => ['Dive Groups', 'Palanquées'],
            'open group planner' => ['Open Group Planner', 'Abrir el planificador de grupos'],
        ];
    }

    #[DataProvider('navChromeStrings')]
    public function test_nav_and_chrome_strings_are_translated_to_spanish(string $key, string $expected): void
    {
        $this->assertSame($expected, __($key), "'{$key}' should resolve to Spanish, not fall back to the source string");
    }

    #[DataProvider('eventPageStrings')]
    public function test_event_page_strings_are_translated_to_spanish(string $key, string $expected): void
    {
        $this->assertSame($expected, __($key), "'{$key}' should resolve to Spanish, not fall back to the source string");
    }

    public function test_es_json_parses_and_every_fr_session_key_has_a_non_stub_translation(): void
    {
        $es = json_decode((string) file_get_contents(lang_path('es.json')), true, 512, JSON_THROW_ON_ERROR);
        $fr = json_decode((string) file_get_contents(lang_path('fr.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach ([...self::navChromeStrings(), ...self::eventPageStrings()] as $label => [$source]) {
            $this->assertArrayHasKey($source, $es, "missing es.json key for {$label}");
            $this->assertNotSame($source, $es[$source], "'{$source}' is present but left untranslated");
            $this->assertArrayHasKey($source, $fr, "fr.json should also have {$label} — test fixtures are out of sync");
        }
    }
}
