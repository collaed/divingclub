## auth.md — Authentication & Authorization

## Authentication Methods

| Method | Implementation | Endpoint |
|--------|---------------|----------|
| Email/password | Laravel native | `/login` |
| OAuth (Google, Facebook, Microsoft, GitHub, LinkedIn) | laravel/socialite | `/auth/{provider}/redirect` → `/auth/{provider}/callback` |
| EU Login (CAS) | Custom CAS client | `/eulogin/redirect` → `/eulogin/callback` |
| API key | Custom header-based | `X-Club-Key-Id` + `X-Club-Secret` |

## Login Security

- Brute-force protection: `failed_login_attempts` table, lockout after N attempts
- Rate limiting: `throttle:5,1` on login route
- Password requirements: enforced at registration (min 8, complexity)
- `must_change_password` flag forces redirect to profile on first login
- Email verification required for all actions (EnsureEmailVerified middleware)

## Roles (Spatie Permission, 6 roles)

| Role | Slug | Access Level |
|------|------|-------------|
| Public | `public` | Unauthenticated visitors |
| Member | `member` | Profile, events, documents, classifieds, votes |
| Instructor | `instructor` | + Instructor calendar, event management, email to participants |
| Bureau Finance | `bureau_finance` | + Payments, bank reconciliation, fees |
| Bureau Technical | `bureau_technical` | + Equipment, dive sites, medical compliance |
| Bureau Master | `bureau_master` | Full access (super admin) |

## Role Checks in Code

```php
// Middleware (routes)
Route::middleware('role:bureau_master,bureau_finance,bureau_technical')->...

// Controller
$this->authorize('...');
abort_unless(auth()->user()->isBureau(), 403);

// Model helpers (User model)
$user->isBureau()          // any bureau_* role
$user->hasRole('instructor')
$user->hasRole('bureau_master')
```

## Member Statuses (6, from member_statuses table)

| Status | Slug | Effect |
|--------|------|--------|
| Membre de droit | `membre_de_droit` | Full active member |
| Actif | `actif` | Active member |
| Fonctionnaire | `fonctionnaire` | Civil servant (fee modifier) |
| Honoraire | `honoraire` | Honorary (no fees) |
| Junior | `junior` | Under 18 (age discount) |
| Famille | `famille` | Family member (discounted) |

Additional statuses in staging: `externe`, `associe`, `assimile`, `sympathisant`, `former`.

## Middleware Stack

| Middleware | Class | Purpose |
|-----------|-------|---------|
| `role:{roles}` | CheckRole | Abort 403 if user lacks any of the listed roles |
| `verified.email` | EnsureEmailVerified | Redirect if email not verified OR must_change_password |
| SetLocale | SetLocale | Detects/sets user's preferred locale |
| EnsureInstalled | EnsureInstalled | Redirects to /install if no users exist |
| StagingBasicAuth | StagingBasicAuth | HTTP basic auth for staging (skipped if no creds configured) |
| CheckLicense | CheckLicense | Blocks registration if license invalid and >100 members |
| ParseEuropeanDates | ParseEuropeanDates | Converts dd/mm/yyyy inputs to Y-m-d before validation |

## Impersonation

Bureau Master can impersonate any member:
- Start: `GET /admin/members/{id}/impersonate` → stores original user in session
- Stop: `GET /admin/stop-impersonation` → restores original user
- UI shows yellow banner during impersonation

## Privacy Rules

- Regular members cannot see other members' email or phone
- Only profile owner + bureau roles can view private contact info
- Member directory shows name + certification level only
- Emergency contacts visible only to bureau and event instructors

## OAuth Provider Config

All configured via `.env`:
- `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET`
- `FACEBOOK_CLIENT_ID` / `FACEBOOK_CLIENT_SECRET`
- `MICROSOFT_CLIENT_ID` / `MICROSOFT_CLIENT_SECRET`
- `GITHUB_CLIENT_ID` / `GITHUB_CLIENT_SECRET`
- `LINKEDIN_CLIENT_ID` / `LINKEDIN_CLIENT_SECRET`

EU Login uses CAS protocol with `EULOGIN_BASE_URL`.

## License System

- RSA-signed license key stored in `config/license.key`
- Public key in `scripts/license-public.pem`
- `LicenseService::needsLicense()` → true if member count > 100
- `LicenseService::isValid()` → verifies RSA signature + expiry
- `CheckLicense` middleware blocks new registrations if invalid
- Free tier: unlimited features, ≤100 members
