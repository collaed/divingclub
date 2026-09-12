<?php

declare(strict_types=1);

namespace App\Helpers;

use Carbon\Carbon;

/**
 * Reads a PDF's own CreationDate via pdfinfo. For FLASSA licence cards this
 * is the date FLASSA actually printed the card — confirmed against 37 real
 * cards on prod: it varies per card, in batches (staff print a run, then
 * stragglers one at a time), not a fixed template stamp — so it is used as
 * the card's issuance/emission date. Not assumed reliable for other
 * document sources without separately verifying the same pattern holds.
 */
class PdfMetadata
{
    public static function creationDate(string $absolutePath): ?Carbon
    {
        if (! file_exists($absolutePath)) {
            return null;
        }

        return self::parseCreationDate((string) shell_exec('pdfinfo '.escapeshellarg($absolutePath).' 2>/dev/null'));
    }

    /** Pure parsing of `pdfinfo` output, split out so it's testable without a real PDF file. */
    public static function parseCreationDate(string $pdfinfoOutput): ?Carbon
    {
        if (! preg_match('/^CreationDate:\s*(.+)$/m', $pdfinfoOutput, $matches)) {
            return null;
        }

        try {
            return Carbon::parse(trim($matches[1]));
        } catch (\Throwable) {
            return null;
        }
    }
}
