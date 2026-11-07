<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PtTranslationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->setLocale('pt');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function navChromeStrings(): array
    {
        return [
            'nav About' => ['About', 'Sobre nós'],
            'nav Resources' => ['Resources', 'Recursos'],
            'nav My Account' => ['My Account', 'A minha conta'],
            'sidebar Classifieds' => ['Classifieds', 'Classificados'],
            'sidebar Buddy Requests' => ['Buddy Requests', 'Pedidos de companheiro'],
            'footer Terms' => ['Terms', 'Condições'],
            'footer Powered by' => ['Powered by', 'Desenvolvido com'],
            'event Edit Layout' => ['Edit Layout', 'Editar esquema'],
            'home View all' => ['View all', 'Ver tudo'],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function eventPageStrings(): array
    {
        return [
            'registration Register another person' => ['Register another person', 'Inscrever outra pessoa'],
            'registration show/hide cancelled' => ['show/hide cancelled', 'mostrar/ocultar cancelados'],
            'participants Instructors' => ['Instructors', 'Instrutores'],
            'gear Lend' => ['Lend', 'Emprestar'],
            'gear weight(s)' => ['weight(s)', 'peso(s)'],
            'payment balance explainer' => ['Positive = you owe the club. Negative = the club owes you.', 'Positivo = deve ao clube. Negativo = o clube deve-lhe a si.'],
            'photos consent' => ["I consent to these photos being shared on the club's social media channels", 'Consinto que estas fotografias sejam partilhadas nas redes sociais do clube'],
            'payment status heading' => ['Payment Status', 'Estado de pagamento'],
            'dive groups heading' => ['Dive Groups', 'Palanquées'],
            'open group planner' => ['Open Group Planner', 'Abrir o planeador de grupos'],
        ];
    }

    /** European Portuguese guard: these Brazilian terms must never appear as a translation value. */
    public function test_no_brazilian_portuguese_terms_leaked_in(): void
    {
        $pt = json_decode((string) file_get_contents(lang_path('pt.json')), true, 512, JSON_THROW_ON_ERROR);
        $brazilianisms = ['Celular', 'Cadastro', 'cadastrado', 'Você está', 'Tela', 'Aplicativo'];

        foreach ($pt as $key => $value) {
            if (! is_string($value)) {
                continue;
            }
            foreach ($brazilianisms as $term) {
                $this->assertStringNotContainsString($term, $value, "pt.json key '{$key}' contains the Brazilian term '{$term}'");
            }
        }
    }

    #[DataProvider('navChromeStrings')]
    public function test_nav_and_chrome_strings_are_translated_to_portuguese(string $key, string $expected): void
    {
        $this->assertSame($expected, __($key), "'{$key}' should resolve to Portuguese, not fall back to the source string");
    }

    #[DataProvider('eventPageStrings')]
    public function test_event_page_strings_are_translated_to_portuguese(string $key, string $expected): void
    {
        $this->assertSame($expected, __($key), "'{$key}' should resolve to Portuguese, not fall back to the source string");
    }

    public function test_pt_json_parses_and_every_fr_session_key_has_a_non_stub_translation(): void
    {
        $pt = json_decode((string) file_get_contents(lang_path('pt.json')), true, 512, JSON_THROW_ON_ERROR);
        $fr = json_decode((string) file_get_contents(lang_path('fr.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach ([...self::navChromeStrings(), ...self::eventPageStrings()] as $label => [$source]) {
            $this->assertArrayHasKey($source, $pt, "missing pt.json key for {$label}");
            $this->assertNotSame($source, $pt[$source], "'{$source}' is present but left untranslated");
            $this->assertArrayHasKey($source, $fr, "fr.json should also have {$label} — test fixtures are out of sync");
        }
    }
}
