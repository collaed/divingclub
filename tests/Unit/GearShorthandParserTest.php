<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GearShorthandParser;
use PHPUnit\Framework\TestCase;

class GearShorthandParserTest extends TestCase
{
    public function test_plain_numeric_item_code(): void
    {
        $tokens = (new GearShorthandParser)->tokenize('t14');

        $this->assertSame([
            ['raw' => 't14', 'kind' => 'item', 'letter' => 't', 'number' => 14],
        ], $tokens);
    }

    public function test_alphanumeric_tank_code_resolves_to_its_digits(): void
    {
        $tokens = (new GearShorthandParser)->tokenize('tM4');

        $this->assertSame([
            ['raw' => 'tM4', 'kind' => 'item', 'letter' => 't', 'number' => 4],
        ], $tokens);
    }

    public function test_bcd_size_prefix_is_equivalent_to_the_bare_number(): void
    {
        $withSize = (new GearShorthandParser)->tokenize('jxs36')[0];
        $bare = (new GearShorthandParser)->tokenize('j36')[0];

        $this->assertSame($bare['kind'], $withSize['kind']);
        $this->assertSame($bare['letter'], $withSize['letter']);
        $this->assertSame($bare['number'], $withSize['number']);
        $this->assertSame(36, $withSize['number']);
    }

    public function test_quantity_shorthand_is_unaffected(): void
    {
        $tokens = (new GearShorthandParser)->tokenize('2w');

        $this->assertSame([
            ['raw' => '2w', 'kind' => 'quantity', 'letter' => 'w', 'number' => 2],
        ], $tokens);
    }

    public function test_mixed_batch_and_invalid_token(): void
    {
        $tokens = (new GearShorthandParser)->tokenize('t14 jxs36 2w ???');

        $this->assertSame('item', $tokens[0]['kind']);
        $this->assertSame('item', $tokens[1]['kind']);
        $this->assertSame('quantity', $tokens[2]['kind']);
        $this->assertSame('invalid', $tokens[3]['kind']);
    }
}
