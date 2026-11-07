<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    /** All locales shipped in config/languages.php must be switchable, not just a hardcoded subset. */
    public function test_every_configured_locale_can_be_switched_to(): void
    {
        foreach (array_keys(config('languages')) as $locale) {
            $this->get("/locale/{$locale}");

            $this->assertSame($locale, session('locale'), "Switching to [{$locale}] did not update the session locale.");
        }
    }

    public function test_an_unknown_locale_is_ignored(): void
    {
        $this->get('/locale/xx');

        $this->assertNull(session('locale'));
    }
}
