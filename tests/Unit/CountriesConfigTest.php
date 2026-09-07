<?php

namespace Tests\Unit;

use Tests\TestCase;

class CountriesConfigTest extends TestCase
{
    public function test_canonical_list_includes_previously_missing_nationalities(): void
    {
        $all = config('countries.all');

        foreach (['Hungary', 'Finland', 'Estonia'] as $country) {
            $this->assertContains($country, $all, "{$country} should be selectable in the canonical country list");
        }
    }

    public function test_common_countries_are_all_present_in_full_list(): void
    {
        $all = config('countries.all');

        foreach (config('countries.common') as $country) {
            $this->assertContains($country, $all, "Common country {$country} must also exist in the full list");
        }
    }

    public function test_country_list_has_no_duplicates(): void
    {
        $all = config('countries.all');

        $this->assertSame(count($all), count(array_unique($all)), 'The canonical country list must not contain duplicates');
    }
}
