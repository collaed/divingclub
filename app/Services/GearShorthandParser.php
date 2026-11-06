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

            if (preg_match('/^([a-z])(\d+)$/i', $raw, $m)) {
                // Letter then digits: a specific numbered item (t14 = tank #14).
                $tokens[] = ['raw' => $raw, 'kind' => 'item', 'letter' => strtolower($m[1]), 'number' => (int) $m[2]];
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
