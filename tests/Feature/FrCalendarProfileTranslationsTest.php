<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FrCalendarProfileTranslationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->setLocale('fr');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function strings(): array
    {
        return [
            'planning heading' => ['Instructor Planning', 'Planning des moniteurs'],
            'planning stamp mode' => ['Stamp mode', 'Mode tampon'],
            'planning week col' => ['Wk', 'Sem.'],
            'legend apnea' => ['Apnea', 'Apnée'],
            'legend quarry' => ['Quarry/Lake', 'Carrière/Lac'],
            'legend long trip' => ['Long Trip', 'Voyage'],
            'event restore' => ['Restore', 'Restaurer'],
            'event cancel occurrence' => ['Cancel occurrence', "Annuler l'occurrence"],
            'profile status set' => ['Status Set (base category)', 'Ensemble de statuts (catégorie de base)'],
            'profile not assigned' => ['— Not assigned —', '— Non attribué —'],
            'language show icons' => ['Show Icons', 'Afficher les icônes'],
            'language club default' => ['Use club default', 'Utiliser le réglage du club'],
            'renewal add licence' => ['Add licence', 'Ajouter une licence'],
            'renewal licence number' => ['Licence #', 'N° de licence'],
            'renewal insurance' => ['Insurance', 'Assurance'],
            'profile age' => ['Age', 'Âge'],
            'profile years' => ['years', 'ans'],
        ];
    }

    #[DataProvider('strings')]
    public function test_calendar_and_profile_strings_are_translated_to_french(string $key, string $expected): void
    {
        $this->assertSame($expected, __($key), "'{$key}' should resolve to French, not fall back to the source string");
    }

    public function test_fr_json_parses_and_keys_are_not_stubs(): void
    {
        $translations = json_decode((string) file_get_contents(lang_path('fr.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach (self::strings() as $label => [$source, $expected]) {
            $this->assertArrayHasKey($source, $translations, "missing fr.json key for {$label}");
            $this->assertNotSame($source, $translations[$source], "'{$source}' is present but left untranslated");
        }
    }
}
