<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Parses the bureau's quick-entry gear shorthand ("t14 r22 j36 2w") into
 * typed tokens. Shared by lending and returning since the grammar is the
 * same either way — only what each side does with a resolved token differs.
 */
final class GearShorthandParser
{
    /**
     * @return list<array{raw: string, kind: 'item'|'quantity'|'invalid', letter: string, number: int}>
     */
    public function tokenize(string $text): array
    {
        $tokens = [];

        foreach (preg_split('/\s+/', trim($text)) as $raw) {
            if ($raw === '') {
                continue;
            }

            if (preg_match('/^([a-z])([a-z0-9]*\d[a-z0-9]*)$/i', $raw, $m)) {
                // Letter then an alphanumeric code containing at least one digit: a specific
                // numbered item. The code's letters are decorative (the tank etched "M4", or a
                // BCD's size prefix like "xs36") — only the digits identify the item, so "tM4"
                // and "jxs36" resolve the same way as "t4" and "j36" would.
                $number = (int) preg_replace('/\D/', '', $m[2]);
                $tokens[] = ['raw' => $raw, 'kind' => 'item', 'letter' => strtolower($m[1]), 'number' => $number];
            } elseif (preg_match('/^(\d+)([a-z])$/i', $raw, $m)) {
                // Digits then letter: a quantity, not a specific item (2w = 2 weights).
                $tokens[] = ['raw' => $raw, 'kind' => 'quantity', 'letter' => strtolower($m[2]), 'number' => (int) $m[1]];
            } else {
                $tokens[] = ['raw' => $raw, 'kind' => 'invalid', 'letter' => '', 'number' => 0];
            }
        }

        return $tokens;
    }
}
