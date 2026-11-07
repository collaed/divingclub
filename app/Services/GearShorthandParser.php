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
     * @return list<array{raw: string, kind: 'item'|'quantity'|'invalid', letter: string, code: string, number: int}>
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
                // item. The code is matched as-is, letters included — a club's inventory can
                // have both a plain-numbered item and a differently-labelled one (tank "7" and
                // tank "M7" are different physical tanks), so the code is never reduced to its
                // digits here. Type-specific matching (e.g. a BCD's optional size prefix) is the
                // calling service's job, since it knows what the letters can mean for that type.
                $tokens[] = ['raw' => $raw, 'kind' => 'item', 'letter' => strtolower($m[1]), 'code' => strtolower($m[2]), 'number' => 0];
            } elseif (preg_match('/^(\d+)([a-z])$/i', $raw, $m)) {
                // Digits then letter: a quantity, not a specific item (2w = 2 weights).
                $tokens[] = ['raw' => $raw, 'kind' => 'quantity', 'letter' => strtolower($m[2]), 'code' => '', 'number' => (int) $m[1]];
            } else {
                $tokens[] = ['raw' => $raw, 'kind' => 'invalid', 'letter' => '', 'code' => '', 'number' => 0];
            }
        }

        return $tokens;
    }
}
