<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('p1')]
class PaginationLangKeysTest extends TestCase
{
    public function test_pagination_keys_resolve_in_english_and_french(): void
    {
        app()->setLocale('en');
        $this->assertStringContainsString('Previous', trans('pagination.previous'));
        $this->assertStringContainsString('Next', trans('pagination.next'));

        app()->setLocale('fr');
        $this->assertStringContainsString('Précédent', trans('pagination.previous'));
        $this->assertStringContainsString('Suivant', trans('pagination.next'));
    }

    public function test_pagination_keys_fall_back_for_the_other_locales(): void
    {
        foreach (['de', 'pt', 'lb', 'sk'] as $locale) {
            app()->setLocale($locale);
            $this->assertNotSame('pagination.previous', trans('pagination.previous'), "unresolved for {$locale}");
            $this->assertNotSame('pagination.next', trans('pagination.next'), "unresolved for {$locale}");
        }
    }
}
