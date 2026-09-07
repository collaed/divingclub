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

        $user = $this->newModelQuery()
            ->where(function ($query) use ($identifier, $lower): void {
                if (str_contains($identifier, '@')) {
                    $query->whereRaw('LOWER(primary_email) = ?', [$lower])
                        ->orWhereHas('emails', fn ($email) => $email
                            ->whereRaw('LOWER(email) = ?', [$lower])
                            ->where('is_verified', true));
                } else {
                    $query->whereNotNull('username')
                        ->whereRaw('LOWER(username) = ?', [$lower]);
                }
            })
            ->first();

        return $user instanceof Authenticatable ? $user : null;
    }
}
