<?php

declare(strict_types=1);

/**
 * ClubCEP.eu — Custom user provider that maps 'email' to 'primary_email'
 * for password reset and other credential lookups, since the users table
 * stores the email in 'primary_email' (not 'email').
 */

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class DivingClubUserProvider extends EloquentUserProvider
{
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (isset($credentials['email'])) {
            $credentials['primary_email'] = $credentials['email'];
            unset($credentials['email']);
        }

        return parent::retrieveByCredentials($credentials);
    }
}
