<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FrProfileEquipmentTranslationsTest extends TestCase
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
            'diving comfort level' => ['Comfort Level', "Niveau d'aisance"],
            'diving air consumption' => ['Air Consumption', "Consommation d'air"],
            'diving primary interest' => ['Primary Dive Interest', 'Intérêt principal en plongée'],
            'diving instructor profile' => ['Instructor Profile', 'Profil de moniteur'],
            'diving specialties' => ['Specialties & Interests', "Spécialités & centres d'intérêt"],
            'equipment sizing' => ['Sizing', 'Tailles'],
            'equipment suit size' => ['Suit Size', 'Taille de combinaison'],
            'equipment quick loan' => ['Quick Loan', 'Prêt rapide'],
            'medical ent' => ['ENT Specialist', 'ORL'],
            'medical sports' => ['Sports Medicine', 'Médecine du sport'],
            'members all federations' => ['All federations', 'Toutes les fédérations'],
            'members csv export' => ['Member List (CSV)', 'Liste des membres (CSV)'],
            'members save failed' => ['Save failed', "Échec de l'enregistrement"],
            'equipment all locations' => ['All Locations', 'Tous les lieux'],
            'equipment last seen' => ['Last Seen', 'Vu pour la dernière fois'],
            'equipment loanable' => ['Loanable', 'Prêtable'],
            'equipment cold' => ['Cold', 'Eau froide'],
        ];
    }

    #[DataProvider('strings')]
    public function test_profile_and_equipment_strings_are_translated_to_french(string $key, string $expected): void
    {
        $this->assertSame($expected, __($key), "'{$key}' should resolve to French, not fall back to the source string");
    }

    public function test_fr_json_parses_and_keys_are_not_stubs(): void
    {
        $translations = json_decode((string) file_get_contents(lang_path('fr.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach (self::strings() as $label => [$source]) {
            $this->assertArrayHasKey($source, $translations, "missing fr.json key for {$label}");
            $this->assertNotSame($source, $translations[$source], "'{$source}' is present but left untranslated");
        }
    }
}
