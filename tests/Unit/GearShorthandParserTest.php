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
            ['raw' => 't14', 'kind' => 'item', 'letter' => 't', 'code' => '14', 'number' => 0],
        ], $tokens);
    }

    public function test_alphanumeric_item_code_is_preserved_verbatim(): void
    {
        // The parser never strips letters from the code — "M4" and "4" must stay
        // distinguishable, since a club's inventory can hold both as separate items.
        $tokens = (new GearShorthandParser)->tokenize('tM4');

        $this->assertSame([
            ['raw' => 'tM4', 'kind' => 'item', 'letter' => 't', 'code' => 'm4', 'number' => 0],
        ], $tokens);
    }

    public function test_bcd_size_prefixed_code_is_preserved_verbatim(): void
    {
        // Splitting the size prefix off a BCD code is the calling service's job
        // (only it knows a BCD's code may carry a size); the parser just tokenizes.
        $tokens = (new GearShorthandParser)->tokenize('jxs36');

        $this->assertSame([
            ['raw' => 'jxs36', 'kind' => 'item', 'letter' => 'j', 'code' => 'xs36', 'number' => 0],
        ], $tokens);
    }

    public function test_quantity_shorthand_is_unaffected(): void
    {
        $tokens = (new GearShorthandParser)->tokenize('2w');

        $this->assertSame([
            ['raw' => '2w', 'kind' => 'quantity', 'letter' => 'w', 'code' => '', 'number' => 2],
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
