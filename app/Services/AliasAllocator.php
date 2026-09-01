<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MailAlias;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Suggests unique, human-readable member mail aliases.
 *
 * Collision cascade (given duplicate first names):
 *   1. firstname                       (e.g. "jean")
 *   2. firstname + last initial        (e.g. "jeand")
 *   3. firstname + more of lastname    (e.g. "jeandu", "jeandup", ...)
 *   4. firstname + full lastname + n   (e.g. "jeandupont2") as a last resort
 *
 * The suggestion is only a proposal; the Bureau confirms or overrides it
 * before it is committed (see the alias management UI).
 */
class AliasAllocator
{
    /**
     * Suggest a unique alias for the given user.
     *
     * @param  int|null  $ignoreAliasId  An existing alias row to exclude from the
     *                                   uniqueness check (when re-suggesting for a
     *                                   member who already owns one).
     */
    public static function suggest(User $user, ?int $ignoreAliasId = null): string
    {
        $first = static::normalize($user->detail?->first_name ?? '');
        $last = static::normalize($user->detail?->last_name ?? '');

        if ($first === '' && $last === '') {
            $first = static::normalize($user->username ?? 'member');
        }

        $candidates = static::candidates($first, $last);

        foreach ($candidates as $candidate) {
            if (static::isAvailable($candidate, $ignoreAliasId)) {
                return $candidate;
            }
        }

        // Last resort: append an incrementing numeric suffix to the fullest form.
        $base = end($candidates) ?: $first;
        $n = 2;
        while (! static::isAvailable($base.$n, $ignoreAliasId)) {
            $n++;
        }

        return $base.$n;
    }

    /**
     * Build the ordered list of candidate aliases from first/last name.
     *
     * @return string[]
     */
    protected static function candidates(string $first, string $last): array
    {
        if ($first === '') {
            return $last !== '' ? [$last] : ['member'];
        }

        $candidates = [$first];

        // firstname + progressively more of the lastname
        $lastLength = mb_strlen($last);
        for ($i = 1; $i <= $lastLength; $i++) {
            $candidates[] = $first.mb_substr($last, 0, $i);
        }

        return array_values(array_unique($candidates));
    }

    /** Whether an alias is free to use. */
    protected static function isAvailable(string $alias, ?int $ignoreAliasId = null): bool
    {
        $query = MailAlias::where('alias', $alias);
        if ($ignoreAliasId !== null) {
            $query->where('id', '!=', $ignoreAliasId);
        }

        return ! $query->exists();
    }

    /**
     * Normalize a name into an alias-safe token: lowercase ASCII, no separators.
     * e.g. "Jean-François" -> "jeanfrancois", "Dupont" -> "dupont".
     */
    protected static function normalize(string $value): string
    {
        $ascii = Str::ascii($value);
        $ascii = strtolower($ascii);

        return (string) preg_replace('/[^a-z0-9]/', '', $ascii);
    }
}
