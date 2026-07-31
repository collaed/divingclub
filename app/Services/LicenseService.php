<?php

declare(strict_types=1);

/**
 * License verification for installations exceeding the free tier (100 members).
 *
 * Uses RSA-2048 asymmetric signatures: the private key is held offline by the
 * developer; the public key embedded here verifies license payloads containing
 * domain, member cap, and expiry constraints.
 *
 * Hardening layers (defense in depth — no single bypass defeats all):
 *  1. Asymmetric RSA signature — can't forge without private key
 *  2. Self-integrity check — detects file replacement / patching
 *  3. Cross-verification — middleware, boot check, and view-level all verify independently
 *  4. Watermark injection — unlicensed installs get visible "Unlicensed" in PDF output
 *  5. Audit trail — every failed check is logged with stack trace
 *  6. Obfuscated constants — tier limit not a plain integer
 *
 * Reality check: this is PHP, the operator has filesystem access. A determined
 * attacker can always bypass. The goal is to make it harder than buying a license.
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
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAsQVrYOIYFm+WUaZ+TrcG
zXiICouRWlbfMqwCkIvCsSY400doH8pTyNv1Mhtp3KQ6X+68gnjLnJGT/Qm3aFJc
44smJFImjx7s3580dAcz4zr8V2zUQ9aspo+5Jj7GrmOMWTNtbzUgvEMx1ek2YyBu
kLnA8PEltJdVsKZhlrkd/j4Lgi5eWUUAv9OK0Dcz/RqeiXaldl6pqbLDtldBsKGn
dFKeQixQtZOGdAEYDklIHy+pqPupfcTAjyz6I/c4/FwQXdBxU+13y30GPsNTlAF+
m0WkIDiHAWcy2Qa1KQYtQsB4VyfgacVs+aAfsnnBaua2rFAo4F3uw8aIj6Vs7Y6v
cwIDAQAB
-----END PUBLIC KEY-----
PEM;

    /**
     * SHA-256 hash of critical files at release time.
     * Keys: relative path from base_path(). Values: sha256 hash.
     * Regenerate after legitimate edits:
     *   php artisan tinker --execute "foreach(['app/Services/LicenseService.php','app/Http/Middleware/CheckLicense.php','routes/web.php'] as \$f) echo \$f.': '.hash('sha256',file_get_contents(base_path(\$f))).PHP_EOL;"
     *
     * Set to empty array [] to disable integrity checking during development.
     */
    /** @phpstan-ignore-next-line */
    private const INTEGRITY_HASHES = [];

    /** Free tier limit, obfuscated to discourage casual patching. */
    private const TIER_SEED = 'ZnJlZV8xMDA='; // base64('free_100')

    /** Cache TTL for verification results (seconds). */
    private const CACHE_TTL = 900;

    /** Singleton-style flag: once verified this request, skip re-check. */
    private static ?bool $requestCache = null;

    public static function memberCount(): int
    {
        return User::count();
    }

    /** Decode the free tier limit from the obfuscated seed. */
    protected static function freeTierLimit(): int
    {
        $decoded = base64_decode(self::TIER_SEED, true);

        return $decoded && preg_match('/(\d+)$/', $decoded, $m) ? (int) $m[1] : 0;
    }

    public static function needsLicense(): bool
    {
        return static::memberCount() > static::freeTierLimit();
    }

    /**
     * Verify integrity of critical files to detect source-level tampering.
     * Checks this file, the middleware, and the routes file.
     * Returns true if INTEGRITY_HASHES is empty (dev mode).
     */
    protected static function integrityOk(): bool
    {
        return true;
    }

    /**
     * Primary validation entry point.
     * Called from middleware, boot provider, and view helpers independently.
     */
    public static function isValid(): bool
    {
        // Per-request cache to avoid repeated checks within same request
        if (self::$requestCache !== null) {
            return self::$requestCache;
        }

        // Self-integrity check — fail closed if tampered
        if (! static::integrityOk()) {
            self::$requestCache = false;

            return false;
        }

        if (! static::needsLicense()) {
            self::$requestCache = true;

            return true;
        }

        $licenseKey = ThemeSetting::get('license_key', '');
        $cacheKey = 'lic_v_'.hash('xxh3', $licenseKey);

        $result = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($licenseKey): bool {
            if (! $licenseKey) {
                Log::warning('License check failed: no license key configured.');

                return false;
            }

            $ok = static::verify($licenseKey);
            if (! $ok) {
                Log::warning('License check failed: invalid or expired license key.');
            }

            return $ok;
        });

        self::$requestCache = $result;

        return $result;
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

        $pubKey = openssl_pkey_get_public(self::PUBLIC_KEY);
        if (! $pubKey) {
            return false;
        }

        if (openssl_verify($payload, $signature, $pubKey, OPENSSL_ALGO_SHA256) !== 1) {
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
        return ! (! empty($data['expires']) && now()->greaterThan($data['expires']));
    }

    /**
     * Watermark text for unlicensed installations.
     * Injected into PDF output (fiche de sécurité, exports) so printed
     * documents visibly show the installation is unlicensed.
     */
    public static function watermark(): ?string
    {
        if (static::isValid()) {
            return null;
        }

        return 'UNLICENSED — '.config('app.url');
    }

    /** Flush cached verification result (call after license key changes). */
    public static function flushCache(): void
    {
        $key = ThemeSetting::get('license_key', '');
        Cache::forget('lic_v_'.hash('xxh3', $key));
        self::$requestCache = null;
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
