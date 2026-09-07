<?php

declare(strict_types=1);

/**
 * ClubCEP.eu — custom user provider.
 *
 * Resolves the login identifier (Laravel's auth + password-reset flows pass it
 * as 'email') to a User by, in order:
 *   1. primary_email          — case-insensitive
 *   2. a *verified* secondary address in user_emails — case-insensitive
 *   3. the legacy `username`  — case-insensitive, may contain spaces. Imported
 *      from the previous system; new registrations do not set one.
 *
 * Password verification is unchanged: EloquentUserProvider::validateCredentials()
 * still hashes-and-compares, so a user with no password (OAuth-only or GDPR
 * erased, where username is nulled) can never authenticate this way.
 */

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class DivingClubUserProvider extends EloquentUserProvider
{
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $identifier = $credentials['email'] ?? $credentials['primary_email'] ?? null;

        if ($identifier === null || is_array($identifier)) {
            return parent::retrieveByCredentials($credentials);
        }

        $identifier = trim((string) $identifier);
        $lower = mb_strtolower($identifier);
        $isEmail = str_contains($identifier, '@');

        // An identifier that looks like an email must actually be one
        // (FILTER_VALIDATE_EMAIL accepts plus-addressing: user+tag@domain.tld).
        if ($isEmail && filter_var($identifier, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        // Exact (case-insensitive) match only — never strip a "+tag", and if the
        // unique indexes ever hold case-variant rows, fail closed rather than
        // authenticate an arbitrary one.
        $matches = $this->newModelQuery()
            ->where(function ($query) use ($isEmail, $lower): void {
                if ($isEmail) {
                    $query->whereRaw('LOWER(primary_email) = ?', [$lower])
                        ->orWhereHas('emails', fn ($email) => $email
                            ->whereRaw('LOWER(email) = ?', [$lower])
                            ->where('is_verified', true));
                } else {
                    $query->whereNotNull('username')
                        ->whereRaw('LOWER(username) = ?', [$lower]);
                }
            })
            ->limit(2)
            ->get();

        $user = $matches->count() === 1 ? $matches->first() : null;

        return $user instanceof Authenticatable ? $user : null;
    }
}
