<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * <x-member-select> renders a hidden id field + a type-to-search text input
 * backed by a <datalist> — the browser matches typed text against option
 * values, so two same-named members would otherwise be indistinguishable.
 */
#[Group('p1')]
class MemberSelectComponentTest extends TestCase
{
    public function test_duplicate_names_are_disambiguated_by_email_but_unique_names_are_not(): void
    {
        $members = collect([
            (object) ['id' => 1, 'name' => 'Jean Dupont', 'primary_email' => 'jean1@x.com'],
            (object) ['id' => 2, 'name' => 'Jean Dupont', 'primary_email' => 'jean2@x.com'],
            (object) ['id' => 3, 'name' => 'Marie Curie', 'primary_email' => 'marie@x.com'],
        ]);

        View::share('errors', new ViewErrorBag);
        $html = Blade::render('<x-member-select name="user_id" :members="$members" />', ['members' => $members]);

        $this->assertStringContainsString('Jean Dupont (jean1@x.com)', $html);
        $this->assertStringContainsString('Jean Dupont (jean2@x.com)', $html);
        $this->assertStringNotContainsString('value="Jean Dupont"', $html);
        $this->assertStringContainsString('value="Marie Curie"', $html);
    }
}
