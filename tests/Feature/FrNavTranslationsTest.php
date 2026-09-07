<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class FrNavTranslationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->setLocale('fr');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function chromeStrings(): array
    {
        return [
            'nav About' => ['About', 'À propos'],
            'nav Resources' => ['Resources', 'Ressources'],
            'nav My Account' => ['My Account', 'Mon compte'],
            'sidebar Minors' => ['Minors', 'Mineurs'],
            'sidebar Trials' => ['Trials', 'Baptêmes'],
            'sidebar Partners' => ['Partners', 'Partenaires'],
            'sidebar Roles' => ['Roles', 'Rôles'],
            'sidebar Backups' => ['Backups', 'Sauvegardes'],
            'dashboard Bureau Worklist' => ['Bureau Worklist', 'Tâches du bureau'],
            'dashboard Scheduled Tasks' => ['Scheduled Tasks', 'Tâches planifiées'],
            'settings Visibility' => ['Visibility', 'Visibilité'],
            'profile Membership Status' => ['Membership Status', "Statut d'adhésion"],
        ];
    }

    /**
     * @dataProvider chromeStrings
     */
    public function test_core_chrome_strings_are_translated_to_french(string $key, string $expected): void
    {
        $this->assertSame($expected, __($key), "'{$key}' should resolve to French, not fall back to the source string");
    }

    public function test_fr_json_is_valid_and_has_no_untranslated_stub_for_these_keys(): void
    {
        $translations = json_decode((string) file_get_contents(lang_path('fr.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach (array_keys(self::chromeStrings()) as $label) {
            [$source] = self::chromeStrings()[$label];
            $this->assertArrayHasKey($source, $translations);
            $this->assertNotSame($source, $translations[$source], "'{$source}' is present but left untranslated");
        }
    }
}
