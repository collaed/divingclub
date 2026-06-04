## license.md — RSA License System

## Overview

Free tier allows up to 100 members. Beyond that, an RSA-signed license key is required. The system uses defense-in-depth: asymmetric signatures, self-integrity checks, cross-verification from multiple call sites, and watermark injection on unlicensed installs.

## Architecture

| Component | Location | Purpose |
|-----------|----------|---------|
| `LicenseService` | `app/Services/LicenseService.php` | Core verification logic |
| `CheckLicense` | `app/Http/Middleware/CheckLicense.php` | Per-request gate |
| License key storage | `theme_settings` table, key `license_key` | Persisted key value |
| Key generation | `scripts/generate-license.php` | Offline tool (private key held by developer) |

## License Format

```
base64(payload).base64(signature)
```

Payload JSON:
```json
{
  "domain": "clubcep.lu",
  "max_members": 200,
  "expires": "2027-06-01"
}
```

Signed with RSA-2048 (OPENSSL_ALGO_SHA256). Public key embedded in LicenseService.

## `LicenseService` Methods

| Method | Purpose |
|--------|---------|
| `memberCount()` | Returns `User::count()` |
| `freeTierLimit()` | Decodes obfuscated limit (base64 of `free_100` → extracts `100`) |
| `needsLicense()` | True if member count exceeds free tier |
| `isValid()` | Primary validation: integrity check → free tier check → verify key |
| `verify(string $license)` | Cryptographic verification: decode, verify RSA signature, check domain/cap/expiry |
| `watermark()` | Returns "UNLICENSED — {url}" if invalid, null if valid |
| `flushCache()` | Clears cached result (call after key changes) |
| `status()` | Returns array with member_count, free_tier_limit, needs_license, is_valid, integrity_ok |

## Verification Flow

```
isValid()
  1. Per-request cache hit? → return cached
  2. Integrity check (SHA-256 of critical files) → fail closed if tampered
  3. needsLicense()? → if ≤100 members, return true (no license needed)
  4. Load license_key from theme_settings
  5. Cache::remember(15 min) → verify(key)
  6. verify():
     a. Split on "." → payload + signature
     b. Base64-decode both
     c. openssl_verify(payload, signature, PUBLIC_KEY, SHA256) === 1?
     d. JSON-decode payload
     e. Check domain binding (config('club.domain'))
     f. Check member cap (max_members >= current count)
     g. Check expiry (expires >= now)
```

## Defense Layers

1. **Asymmetric RSA signature** — can't forge without private key
2. **Self-integrity check** — SHA-256 of LicenseService.php, CheckLicense.php, routes/web.php (disabled in dev via empty INTEGRITY_HASHES)
3. **Cross-verification** — middleware, boot check, and view-level all verify independently
4. **Watermark injection** — unlicensed installs get visible "Unlicensed" text in PDF output
5. **Audit trail** — failed checks logged with stack trace
6. **Obfuscated constants** — tier limit encoded as base64, not a plain integer

## Middleware: `CheckLicense`

Applied to registration routes. If `needsLicense()` and `!isValid()`, blocks new user registration with an appropriate error. Does not block login or existing member access.

## Caching

- Result cached for 900 seconds (15 minutes) keyed on `lic_v_{xxh3(key)}`
- Per-request static cache avoids repeated checks within one HTTP request
- `flushCache()` called when admin updates the license key

## Admin UI

Bureau can view license status and enter/update the license key via Admin → Settings. Shows member count, free tier limit, and current validity.
