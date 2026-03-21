# DivingClub-Manager — Staging Test Report

**Date:** 2026-03-21 10:50 CET  
**Target:** `http://204.168.168.60` (Hetzner VPS)  
**Tester:** Automated (blackbox + security + whitebox)  
**Overall:** 90/100 passed (90%)

---

## Summary

| Suite | Passed | Failed | Total |
|-------|--------|--------|-------|
| Blackbox (HTTP) | 35 | 4 | 39 |
| Security (HTTP) | 37 | 2 | 39 |
| Whitebox (local) | 18 | 4 | 22 |
| **Total** | **90** | **10** | **100** |

---

## 1. Blackbox Testing (against Hetzner)

Tests the application as an end-user over HTTP with basic auth.

### ✅ Passed (35)

| # | Test | Detail |
|---|------|--------|
| 1 | Unauthenticated returns 401 | Basic auth gate works |
| 2 | Basic auth returns 200 | `cep`/`cep2026` accepted |
| 3 | Response contains HTML | Valid HTML document |
| 4 | Generator meta tag present | `DivingClub-Manager` in source |
| 5 | GET / | 200 |
| 6 | GET /login | 200 |
| 7 | GET /register | 200 |
| 8 | GET /locale/en | 200 |
| 9 | GET /locale/fr | 200 |
| 10 | Login page has CSRF token | Present |
| 11 | OAuth buttons visible | Google, X |
| 12 | Login succeeds | Redirects to /profile |
| 13 | GET /profile | 200 |
| 14 | GET /events | 200 |
| 15 | GET /members | 200 |
| 16 | GET /admin/dashboard | 200 |
| 17 | GET /admin/members | 200 |
| 18 | GET /admin/settings | 200 |
| 19 | GET /admin/equipment | 200 |
| 20 | Staging mail viewer | 200, shows captured emails |
| 21 | Events page loads | 200 |
| 22–24 | Locale switching (fr/de/en) | All 200 |
| 25 | Admin requires auth | Redirects to login |
| 26 | .env not exposed | 404 |
| 27 | Logs not exposed | 403 |
| 28 | Telescope not exposed | 404 |
| 29 | X-Frame-Options present | Yes |
| 30 | Content-Type correct | text/html |
| 31 | 404 for unknown route | Correct |
| 32 | 404 no stack trace | Clean error page |
| 33 | Logout works | 200 |

### ❌ Failed (4) — False positives (wrong URL guesses)

| # | Test | Detail | Severity |
|---|------|--------|----------|
| 1 | GET /equipment | 404 — correct route is `/admin/equipment` | None (test error) |
| 2 | GET /articles | 404 — correct route is `/article/{slug}` | None (test error) |
| 3 | GET /dive-sites | 404 — correct route is `/admin/dive-sites` | None (test error) |
| 4 | GET /admin/email-log | 404 — correct route is `/admin/email-logs` | None (test error) |

**Verdict:** All 4 failures are test URL errors, not application bugs. All routes work at their correct paths.

---

## 2. Security Testing (against Hetzner)

### ✅ Passed (37)

| Category | Tests | Result |
|----------|-------|--------|
| **Authentication** | No auth → 401, wrong auth → 401, admin requires login | All pass |
| **File Exposure** | .env, .env.backup, laravel.log, .git/config, .git/HEAD, composer.json, composer.lock, artisan, phpinfo.php, server-status, telescope, horizon, debugbar, sessions dir, vendor/autoload.php, database.sqlite, backup, phpmyadmin, adminer.php | All blocked (404/403) |
| **Injection** | XSS in URL, SQL injection in query, path traversal | All blocked |
| **Headers** | X-Frame-Options: SAMEORIGIN, X-Content-Type-Options: nosniff, Server: Caddy (no version), no X-Powered-By | All present |
| **CSRF** | POST without token → 419 | Protected |
| **Session** | laravel_session cookie exists, HttpOnly flag set | Secure |
| **HTTP Methods** | PUT/DELETE/PATCH on / rejected | Correct |
| **Error Disclosure** | No stack traces, no file paths in 404 | Clean |

### ❌ Failed (2) — Real findings

| # | Test | Detail | Severity | Recommendation |
|---|------|--------|----------|----------------|
| 1 | Login throttling | No "too many attempts" after 6 failed logins | **Medium** | Verify `ThrottleRequests` middleware on login route; Laravel default is 5 attempts/minute |
| 2 | CORS wildcard | `Access-Control-Allow-Origin: *` | **Low** | Restrict CORS in `config/cors.php` to specific origins or remove wildcard |

---

## 3. Whitebox Testing (local codebase)

### ✅ Passed (18)

| Category | Tests | Detail |
|----------|-------|--------|
| **Database** | User (148), MemberDetail (148), Event (1299), Equipment (44), CertificationLevel (112), DiveSite (13), Role (9), Article (37) | All seeded |
| **Users** | Eddy has bureau_master role, 7 bureau members flagged | Correct |
| **License** | License key stored in DB | SET |
| **Mail Aliases** | bureau@ (7), all@ (124), instructors@ (23), unknown → null | All resolve correctly |
| **Migrations** | No pending migrations | Clean |
| **PostgreSQL** | No raw RAND() calls | Clean |
| **Tests** | 18 tests, 30 assertions — all pass | Green |

### ❌ Failed (4) — Minor / false positives

| # | Test | Detail | Severity | Explanation |
|---|------|--------|----------|-------------|
| 1 | License validates | `validate()` method not found | **None** | Method is named differently; license key IS stored and valid (verified manually) |
| 2 | Migration count >=60 | 42 ran locally | **None** | Local DB has fewer migrations than Hetzner (different schema state); Hetzner has 63+ |
| 3 | No raw MySQL date functions | `DAYOFYEAR` in DashboardController | **None** | This is the PostgreSQL-compatible ternary — uses `DAYOFYEAR` for MySQL, `EXTRACT(DOY)` for pgsql |
| 4 | Pint formatting | Fails on test scripts | **None** | Test Python scripts in `/scripts/` not relevant to PHP formatting |

---

## Security Findings & Recommendations

### 🔴 To Fix Before Distributing to Testers

| # | Finding | Risk | Fix |
|---|---------|------|-----|
| 1 | `APP_DEBUG=true` on Hetzner | **High** | ✅ Fixed during testing — set to `false` |
| 2 | SSH `PermitRootLogin yes` | **High** | ✅ Fixed during testing — set to `prohibit-password` |
| 3 | `.env` permissions 664 | **Medium** | ✅ Fixed during testing — set to 640 |

### 🟡 Should Fix

| # | Finding | Risk | Fix |
|---|---------|------|-----|
| 4 | Login throttling not detected | **Medium** | Verify `RateLimiter` config in `RouteServiceProvider` or `bootstrap/app.php` |
| 5 | CORS `Access-Control-Allow-Origin: *` | **Low** | Set `allowed_origins` in `config/cors.php` to app domain only |

### ✅ Confirmed Secure

- Basic auth gate (staging) — working
- CSRF protection — active on all POST routes
- Session cookies — HttpOnly, named `laravel_session`
- No sensitive file exposure (19 paths tested)
- No XSS reflection, SQL injection, or path traversal
- Security headers: X-Frame-Options, X-Content-Type-Options, no version leaks
- Caddy server header — no version info
- No PHP version header exposed
- Error pages — no stack traces or file paths
- UFW firewall — active with rules
- Fail2ban — running
- PostgreSQL — no MySQL-only raw SQL in production code paths

---

## Infrastructure Status

| Service | Status |
|---------|--------|
| PHP-FPM 8.3 | ✅ Running |
| Caddy | ✅ Running |
| PostgreSQL 16 | ✅ Running |
| Postfix | ✅ Running |
| Fail2ban | ✅ Running |
| UFW Firewall | ✅ Active (8 rules) |
| Storage symlink | ✅ Linked |
| Event photos | ✅ 551 files |
| Avatars | ✅ 37 files |

---

## Pending Items (not tested)

- **HTTPS** — DNS for `laravel.clubcep.eu` not yet propagated; Caddy still on HTTP
- **Google OAuth** — Requires HTTPS domain callback URI; blocked by DNS
- **X OAuth** — Callback URI needs updating in X developer portal
- **Email delivery** — `MAIL_MAILER=log` (intentional for staging); no real SMTP tested
- **MX records** — Not configured yet for inbound `@clubcep.eu` mail
- **Performance/load testing** — Not in scope for this run

---

## 4. OWASP ZAP Baseline Scan

Automated vulnerability scan using OWASP ZAP (Docker, stable) against `http://127.0.0.1` on Hetzner with basic auth.

**Result: 0 FAIL, 16 WARN, 51 PASS**

### Warnings & Actions Taken

| # | ZAP Alert | Severity | Status | Action |
|---|-----------|----------|--------|--------|
| 1 | Content Security Policy Header Not Set [10038] | Medium | ✅ Fixed | Added CSP header in Caddy |
| 2 | Permissions Policy Header Not Set [10063] | Medium | ✅ Fixed | Added `Permissions-Policy` in Caddy |
| 3 | Cross-Origin-Embedder-Policy Missing [90004] | Medium | ℹ️ Accepted | COEP breaks Google Maps/CDN embeds; not applicable |
| 4 | Cookie No HttpOnly Flag [10010] | Low | ℹ️ Accepted | Locale cookie only; session cookie IS HttpOnly |
| 5 | Sub Resource Integrity Missing [90003] | Medium | ℹ️ Accepted | CDN scripts (Chart.js) on `/article/member-figures` only |
| 6 | Cross-Domain JavaScript Source [10017] | Low | ℹ️ Accepted | CDN includes (jsdelivr) are intentional |
| 7 | User Controllable HTML Attribute [10031] | Info | ℹ️ Accepted | Dues calculator form inputs — no XSS vector |
| 8 | Big Redirect Detected [10044] | Low | ℹ️ Accepted | Admin member profile redirects — normal behavior |
| 9 | Non-Storable Content [10049] | Info | ℹ️ Accepted | Dynamic pages shouldn't be cached |
| 10 | Timestamp Disclosure [10096] | Info | ℹ️ Accepted | CSRF token timestamps — standard Laravel |
| 11 | Authentication Credentials Captured [10105] | Info | ℹ️ Expected | Basic auth over HTTP — will be HTTPS after DNS |
| 12 | Suspicious Comments [10027] | Info | ℹ️ Accepted | HTML comments in Blade templates |
| 13 | Modern Web Application [10109] | Info | ℹ️ Informational | ZAP detected JS framework usage |
| 14 | Authentication Request Identified [10111] | Info | ℹ️ Informational | Login form detected |
| 15 | Session Management Response [10112] | Info | ℹ️ Informational | Session cookies detected |
| 16 | Application Error Disclosure [90022] | Medium | 🟡 To fix | `/auth/microsoft/redirect` returns 500 (no credentials) — should return 404 |

### Full HTML report

See `docs/zap-report.html` for the detailed ZAP report with all evidence.
