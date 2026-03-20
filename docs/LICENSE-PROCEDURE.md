# License Key Generation Procedure

How to generate and install a license key for DivingClub-Manager instances exceeding the free tier (100 members).

---

## Prerequisites

- OpenSSL installed on your machine
- Access to the server's Admin → Settings panel
- The RSA private key (kept offline, never on the server)

## Step 1 — Generate the RSA Key Pair (Once)

```bash
# Generate private key (keep this SECRET — never commit or upload)
openssl genrsa -out license-private.pem 2048

# Extract public key
openssl rsa -in license-private.pem -pubout -out license-public.pem
```

Store `license-private.pem` in a secure location (encrypted USB, password manager vault). You only need it when generating new licenses.

## Step 2 — Install the Public Key

Copy the contents of `license-public.pem` into `app/Services/LicenseService.php`, replacing the `PUBLIC_KEY` constant:

```php
private const PUBLIC_KEY = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8A...  ← your actual key
-----END PUBLIC KEY-----
PEM;
```

Commit and deploy. This only needs to be done once.

## Step 3 — Generate a License Key

### For a small club (≤20 members)

No license needed — the free tier covers up to 100 members. Skip this entirely.

### For a club with up to 20 members (explicit license)

```bash
php scripts/generate-license.php license-private.pem clubdomain.eu 20 2027-12-31
```

### For a club with up to 500 members

```bash
php scripts/generate-license.php license-private.pem clubdomain.eu 500 2027-12-31
```

### Parameters

| Argument | Description | Example |
|----------|-------------|---------|
| `license-private.pem` | Path to your private key file | `~/keys/license-private.pem` |
| `clubdomain.eu` | The club's domain (must match `config('club.domain')`) | `clubcep.eu` |
| `500` | Maximum member count allowed | `20`, `100`, `500`, `9999` |
| `2027-12-31` | Expiry date (optional, defaults to +13 months) | `2028-06-30` |

### Output

The script prints a license key like:

```
License Key:
eyJkb21haW4iOiJjbHViY2VwLmV1IiwibWF4X21lbWJlcnMiOjUwMCwiZXhwaXJlcyI6IjIwMjctMTItMzEiLCJpc3N1ZWRfYXQiOiIyMDI2LTAzLTIwIDEwOjAwOjAwIn0=.SIGNATURE_BASE64_HERE

Payload: {"domain":"clubcep.eu","max_members":500,"expires":"2027-12-31","issued_at":"2026-03-20 10:00:00"}
Valid for: clubcep.eu, up to 500 members, expires 2027-12-31
```

## Step 4 — Install the License Key

1. Log in as Bureau Master
2. Go to **Administration → Settings → License**
3. Paste the entire license key string (the `eyJ...` line) into the License Key field
4. Click **Save**
5. The page should now show: ✅ License valid

## Verification

The license is verified at three independent checkpoints:
- **Middleware** — blocks member registration when invalid
- **Boot provider** — shares watermark status with all views
- **PDF output** — stamps "UNLICENSED" on fiche de sécurité and exports if invalid

To check status programmatically:

```bash
php artisan tinker --execute "\App\Services\LicenseService::status();"
```

Returns:
```php
[
    'member_count' => 142,
    'free_tier_limit' => 100,
    'needs_license' => true,
    'is_valid' => true,
    'integrity_ok' => true,
]
```

## Renewal

Licenses default to 13 months. To renew, generate a new key with a future expiry date and paste it into Admin → Settings → License. The old key is replaced immediately.

## Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| "Invalid license" after deploy | Public key mismatch | Ensure `PUBLIC_KEY` in LicenseService matches the key pair used to sign |
| "Invalid license" after code update | Integrity hash mismatch | Regenerate `INTEGRITY_HASHES` or set to `[]` during development |
| License valid but watermark shows | Cache stale | Clear cache: `php artisan cache:clear` |
| Domain mismatch | `config('club.domain')` differs from license payload | Update `.env` or generate a new key with the correct domain |

## Security Notes

- The private key is **never** stored on the server or in the repository
- License payloads are signed with RSA-2048 + SHA-256 — cannot be forged without the private key
- The system fails closed: if verification fails for any reason, the installation is treated as unlicensed
- Tampering with `LicenseService.php`, `CheckLicense.php`, or `routes/web.php` triggers integrity alerts (when hashes are configured)
