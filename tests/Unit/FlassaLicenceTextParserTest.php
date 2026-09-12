<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\FlassaLicenceTextParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('p1')]
class FlassaLicenceTextParserTest extends TestCase
{
    private const REAL_CARD_TEXT = <<<'TEXT'
        Fédération Luxembourgeoise
        des Activités et Sports
        Sub-Aquatiques

        Licence

        2026LS-0409

        CLUB EUROPEEN DE PLONGEE
        COLLART Eddy - 03.08.1975
        15c, rue Meckenheck L-3321 BERCHEM
        Licence basée sur certificat médical / Medical certificate based license
        TEXT;

    public function test_parses_the_real_card_layout(): void
    {
        $fields = FlassaLicenceTextParser::parse(self::REAL_CARD_TEXT);

        $this->assertSame(['name' => 'COLLART Eddy', 'licence_number' => 'LS-0409', 'year' => '2026'], $fields);
    }

    public function test_handles_a_multi_word_last_name(): void
    {
        $text = str_replace('COLLART Eddy - 03.08.1975', 'VAN DER BERG Marie - 12.01.1990', self::REAL_CARD_TEXT);

        $fields = FlassaLicenceTextParser::parse($text);

        $this->assertSame('VAN DER BERG Marie', $fields['name']);
    }

    public function test_returns_null_for_unrelated_text(): void
    {
        $this->assertNull(FlassaLicenceTextParser::parse('This is not a licence card at all.'));
    }

    public function test_returns_null_when_the_number_is_present_but_no_name_line_matches(): void
    {
        $this->assertNull(FlassaLicenceTextParser::parse("2026LS-0409\n\nsomething else entirely"));
    }
}
