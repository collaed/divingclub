<?php

namespace App\Services;

use App\Models\ThemeSetting;
use App\Models\User;

class LicenseService
{
    /**
     * The public key used to verify license signatures.
     * The club operator signs license codes with their private key;
     * this public key verifies them.
     */
    private const PUBLIC_KEY = <<<'PEM'
-----BEGIN PUBLIC KEY-----
REPLACE_WITH_YOUR_PUBLIC_KEY
-----END PUBLIC KEY-----
PEM;

    private const FREE_TIER_LIMIT = 100;

    public static function memberCount(): int
    {
        return User::count();
    }

    public static function needsLicense(): bool
    {
        return static::memberCount() > static::FREE_TIER_LIMIT;
    }

    public static function isValid(): bool
    {
        if (!static::needsLicense()) {
            return true;
        }

        $license = ThemeSetting::get('license_key');
        if (!$license) {
            return false;
        }

        return static::verify($license);
    }

    /**
     * Verify a license key.
     * License format: base64(payload).base64(signature)
     * Payload JSON: {"domain": "clubcep.eu", "max_members": 500, "expires": "2027-12-31"}
     */
    public static function verify(string $license): bool
    {
        $parts = explode('.', $license);
        if (count($parts) !== 2) {
            return false;
        }

        [$payloadB64, $signatureB64] = $parts;
        $payload = base64_decode($payloadB64, true);
        $signature = base64_decode($signatureB64, true);

        if (!$payload || !$signature) {
            return false;
        }

        // Verify signature with public key
        $pubKey = openssl_pkey_get_public(static::PUBLIC_KEY);
        if (!$pubKey) {
            return false;
        }

        $valid = openssl_verify($payload, $signature, $pubKey, OPENSSL_ALGO_SHA256);
        if ($valid !== 1) {
            return false;
        }

        // Parse and check constraints
        $data = json_decode($payload, true);
        if (!$data) {
            return false;
        }

        // Check domain matches
        $domain = config('club.domain', '');
        if (!empty($data['domain']) && $data['domain'] !== $domain) {
            return false;
        }

        // Check member cap
        if (!empty($data['max_members']) && static::memberCount() > (int) $data['max_members']) {
            return false;
        }

        // Check expiry
        if (!empty($data['expires']) && now()->greaterThan($data['expires'])) {
            return false;
        }

        return true;
    }

    public static function status(): array
    {
        return [
            'member_count' => static::memberCount(),
            'free_tier_limit' => static::FREE_TIER_LIMIT,
            'needs_license' => static::needsLicense(),
            'is_valid' => static::isValid(),
        ];
    }
}
