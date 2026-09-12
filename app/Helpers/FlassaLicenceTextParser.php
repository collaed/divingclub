<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * FLASSA licence cards are a generated PDF with a real text layer, not a
 * scan — pdftotext reads them directly, so no OCR/vision call is needed to
 * pull the holder name, licence number, and season year off one. Isolated
 * as a pure function (no shell-out, no I/O) so the parsing itself is
 * unit-testable without a real PDF or the pdftotext binary.
 */
class FlassaLicenceTextParser
{
    /**
     * @return array{name: ?string, licence_number: ?string, year: ?string}|null null only if text has no licence structure at all; otherwise returns what was extracted (fields may be null if not found).
     */
    public static function parse(string $text): ?array
    {
        $result = ['name' => null, 'licence_number' => null, 'year' => null];

        // Licence number + season year are on one line, e.g. "2026LS-0409".
        if (preg_match('/(\d{4})\s*([A-Z]{2}-\d{3,5})/', $text, $numberMatch)) {
            $result['licence_number'] = $numberMatch[2];
            $result['year'] = $numberMatch[1];
        }

        // Holder line, e.g. "COLLART Eddy - 03.08.1975" (LASTNAME Firstname - DOB).
        if (preg_match('/^([A-ZÀ-ÖØ-Þ][A-ZÀ-ÖØ-Þ\'\- ]*?)\s+([A-ZÀ-ÖØ-Þ][a-zà-öø-þ\'\-]+)\s*-\s*\d{2}\.\d{2}\.\d{4}\s*$/mu', $text, $nameMatch)) {
            $result['name'] = trim($nameMatch[1]).' '.trim($nameMatch[2]);
        }

        return (count(array_filter($result)) > 0) ? $result : null;
    }
}
