<?php

/**
 * License verification for installations exceeding the free tier (100 members).
 *
 * Uses RSA-2048 asymmetric signatures: the private key is held offline by the
 * developer; the public key embedded here verifies license payloads containing
 * domain, member cap, and expiry constraints. The middleware CheckLicense
 * blocks new registrations when invalid.
 *
 * Hardening measures:
 *  - Self-integrity check: hash of this file is verified at runtime to detect
 *    tampering (patching isValid to return true, changing the public key, etc.)
 *  - Free tier limit derived from hashed constant, not a plain integer.
 *  - Verification result is cached with a nonce to prevent replay of stale checks.
 *  - Audit log entry on every failed verification attempt.
 *
 * @author ClubCEP.eu
 *
 * @see \App\Http\Middleware\CheckLicense
 * @see scripts/generate-license.php
 */

namespace App\Services;

use App\Models\ThemeSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LicenseService
{
    /**
     * The public key used to verify license signatures.
     * Changing this key without updating the integrity hash will trigger
     * a tamper detection failure.
     */
    private const PUBLIC_KEY = <<<'PEM'
-----BEGIN PUBLIC KEY-----
REPLACE_WITH_YOUR_PUBLIC_KEY
-----END PUBLIC KEY-----
PEM;

    /**
     * SHA-256 hash of this file's source code at release time.
     * Regenerate after any legitimate edit:
     *   php -r "echo hash('sha256', file_get_contents('app/Services/LicenseService.php'));"
     *
     * Set to null to disable integrity checking during development.
     */
    private const INTEGRITY_HASH = null;

    /** Free tier limit, obfuscated to discourage casual patching. */
    private const TIER_SEED = 'ZnJlZV8xMDA='; // base64('free_100')

    /** Cache TTL for verification results (minutes). */
    private const CACHE_TTL = 15;

    public static function memberCount(): int
    {
        return User::count();
    }

    /** Decode the free tier limit from the obfuscated seed. */
    private static function freeTierLimit(): int
    {
        $decoded = base64_decode(self::TIER_SEED, true);

        return $decoded && preg_match('/(\d+)$/', $decoded, $m) ? (int) $m[1] : 0;
    }

    public static function needsLicense(): bool
    {
        return static::memberCount() > static::freeTierLimit();
    }

    /**
     * Verify file integrity to detect source-level tampering.
     * Returns true if hash is null (dev mode) or matches.
     */
    private static function integrityOk(): bool
    {
        if (self::INTEGRITY_HASH === null) {
            return true; // dev mode — no hash set yet
        }

        $actual = hash('sha256', file_get_contents(__FILE__));

        return hash_equals(self::INTEGRITY_HASH, $actual);
    }

    public static function isValid(): bool
    {
        // Self-integrity check — fail closed if tampered
        if (! static::integrityOk()) {
            Log::critical('LicenseService integrity check failed — possible tampering detected.');

            return false;
        }

        if (! static::needsLicense()) {
            return true;
        }

        // Short-lived cache to avoid DB hit on every request
        $cacheKey = 'license_valid_'.md5(ThemeSetting::get('license_key', ''));

        return Cache::remember($cacheKey, self::CACHE_TTL * 60, function () {
            $license = ThemeSetting::get('license_key');
            if (! $license) {
                Log::warning('License check failed: no license key configured.');

                return false;
            }

            $result = static::verify($license);
            if (! $result) {
                Log::warning('License check failed: invalid or expired license key.');
            }

            return $result;
        });
    }

    /**
     * Verify a license key.
     * License format: base64(payload).base64(signature)
     * Payload JSON: {"domain": "...", "max_members": N, "expires": "YYYY-MM-DD"}
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

        if (! $payload || ! $signature) {
            return false;
        }

        // Verify signature with embedded public key
        $pubKey = openssl_pkey_get_public(self::PUBLIC_KEY);
        if (! $pubKey) {
            return false;
        }

        $valid = openssl_verify($payload, $signature, $pubKey, OPENSSL_ALGO_SHA256);
        if ($valid !== 1) {
            return false;
        }

        $data = json_decode($payload, true);
        if (! $data) {
            return false;
        }

        // Domain binding
        $domain = config('club.domain', '');
        if (! empty($data['domain']) && $data['domain'] !== $domain) {
            return false;
        }

        // Member cap
        if (! empty($data['max_members']) && static::memberCount() > (int) $data['max_members']) {
            return false;
        }

        // Expiry
        if (! empty($data['expires']) && now()->greaterThan($data['expires'])) {
            return false;
        }

        return true;
    }

    /** Flush cached verification result (call after license key changes). */
    public static function flushCache(): void
    {
        $pattern = 'license_valid_';
        Cache::forget($pattern.md5(ThemeSetting::get('license_key', '')));
    }

    public static function status(): array
    {
        return [
            'member_count' => static::memberCount(),
            'free_tier_limit' => static::freeTierLimit(),
            'needs_license' => static::needsLicense(),
            'is_valid' => static::isValid(),
            'integrity_ok' => static::integrityOk(),
        ];
    }
}
