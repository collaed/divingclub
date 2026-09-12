<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\PdfMetadata;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('p1')]
class PdfMetadataTest extends TestCase
{
    public function test_parses_the_real_pdfinfo_creation_date_format(): void
    {
        // Verbatim shape of `pdfinfo`'s own output, captured from a real
        // FLASSA card (see PdfMetadata's docblock for why this is trusted).
        $output = <<<'TXT'
            Title:           Enter name
            Author:          Dominique
            Creator:         PScript5.dll Version 5.2.2
            Producer:        Acrobat Distiller 20.0 (Windows)
            CreationDate:    Thu Feb 12 13:54:34 2026 CET
            ModDate:         Thu Feb 12 13:54:34 2026 CET
            Pages:           1
            TXT;

        $date = PdfMetadata::parseCreationDate($output);

        $this->assertNotNull($date);
        $this->assertSame('2026-02-12 13:54:34', $date->format('Y-m-d H:i:s'));
    }

    public function test_returns_null_when_creation_date_is_absent(): void
    {
        $output = "Title:           Enter name\nAuthor:          Dominique\nPages:           1";

        $this->assertNull(PdfMetadata::parseCreationDate($output));
    }

    public function test_returns_null_for_empty_output(): void
    {
        $this->assertNull(PdfMetadata::parseCreationDate(''));
    }

    public function test_returns_null_for_an_unparseable_date(): void
    {
        $output = "CreationDate:    not a real date\n";

        $this->assertNull(PdfMetadata::parseCreationDate($output));
    }

    public function test_creation_date_returns_null_for_a_missing_file(): void
    {
        $this->assertNull(PdfMetadata::creationDate('/tmp/does-not-exist-'.uniqid().'.pdf'));
    }
}
