# DivingClub-Manager — Whitebox Testing Report
**Date:** 2026-03-21
**Scope:** Security, code quality, data integrity, test coverage
**Method:** Static analysis of routes, controllers, models, views, services, migrations

---

## PRIORITY 1 — Security Issues

### 1.1 🔴 Backup Download — Path Traversal
**File:** `app/Http/Controllers/Admin/BackupController.php`
**Risk:** HIGH
The `download()` and `show()` methods accept a raw `$filename` string parameter and concatenate it directly into `storage_path("app/backups/{$filename}")`. No `basename()` call or path validation.
An attacker with bureau_master access could request `../../.env` to download the environment file containing database credentials and secrets.
**Fix:** Add `$filename = basename($filename);` as the first line in both `download()` and `show()`.

### 1.2 🔴 Comment Body — Stored XSS
**File:** `resources/views/cms/partials/comment.blade.php:18`
**Risk:** HIGH
Comment body is rendered with `{!! $comment->body !!}` (unescaped). While the `CommentController` runs HTMLPurifier on input, the view has no defense-in-depth. If a comment is inserted via seeder, tinker, or a future code path that bypasses the controller, raw HTML/JS will execute.
**Fix:** Either always purify on output (`{!! clean($comment->body) !!}`) or switch to `{{ }}` if HTML formatting isn't needed in comments.

### 1.3 🟠 Article Body — No Server-Side Sanitization
**File:** `app/Models/Article.php` → `renderedBody()`
**Risk:** MEDIUM
Article body is stored as raw HTML (from a rich text editor) and rendered via `{!! $article->renderedBody() !!}`. The `renderedBody()` method only does YouTube/Vimeo embed conversion — no HTMLPurifier or sanitization. Bureau users can inject arbitrary HTML/JS.
**Mitigation:** Only bureau_master can create articles, so the trust boundary is smaller. But if the account is compromised, it's a full XSS vector.
**Fix:** Run HTMLPurifier on article body before rendering, allowing a safe subset of HTML tags.

### 1.4 🟠 Forgot Password — No Rate Limiting
**File:** `routes/web.php:99`
**Risk:** MEDIUM
The `POST /forgot-password` route has no `throttle` middleware. An attacker can enumerate emails or flood the mail queue. Login (`throttle:5,1`) and email verification (`throttle:6,1`) are throttled, but password reset is not.
**Fix:** Add `->middleware('throttle:5,1')` to the forgot-password POST route.

### 1.5 🟠 Install Route — Weak Protection
**File:** `app/Http/Controllers/InstallController.php`
**Risk:** MEDIUM
The install wizard is only gated by checking if the `users` table has rows. If the database is wiped or a new database is connected, the install route becomes accessible to anyone, allowing full admin account creation and `.env` rewrite.
**Fix:** Add a file-based lock (e.g., `storage/installed.lock`) that persists independently of the database.

### 1.6 🟡 Federation API — No Rate Limiting
**File:** `routes/api.php`
**Risk:** LOW-MEDIUM
The federation API endpoints (`/api/federation/*`) have no throttle middleware. While they require `X-Club-Key-Id` + `X-Club-Secret` headers with hashed verification, brute-force attempts against the API key are not rate-limited.
**Fix:** Add `throttle:30,1` middleware to the federation route group.

### 1.7 🟡 Classifieds Body — Unescaped in Index
**File:** `resources/views/classifieds/index.blade.php:52`
**Risk:** LOW
`{!! Str::limit(strip_tags($ad->body), 200) !!}` — while `strip_tags()` removes HTML, it's not a reliable sanitizer (can be bypassed with malformed tags in some edge cases). The `{!! !!}` is unnecessary here since `strip_tags` output should be plain text.
**Fix:** Use `{{ Str::limit(strip_tags($ad->body), 200) }}` (escaped output).

---

## PRIORITY 2 — Code Quality Issues

### 2.1 🟠 Zero Eager Loading in Controllers
**Files:** All controllers in `app/Http/Controllers/`
**Risk:** N+1 query performance
Not a single controller uses `->with()` for eager loading. Every relationship access in loops (registrations, members, details) triggers individual queries. This will degrade significantly with real data volumes.
**Key offenders:**
- `EventController` — loops over registrations accessing `->user->detail`
- `DashboardController` — multiple relation accesses in worklist queries
- `DiveGroupController` — accesses member details per group
**Fix:** Add `->with()` calls to queries that feed loops/collections.

### 2.2 🟠 DB:: Facade Used in 9 Controllers
**Files:** InstallController, VotePublicController, DashboardController, SeasonController, EventController, RegisterController, LoginController, SocialAuthController, ProfileController
**Risk:** Bypasses Eloquent protections, harder to test
Most uses are for `DB::transaction()` (acceptable) or `DB::raw()` (needed for complex queries). However, `ProfileController:82` uses raw `DB::table()` update which bypasses model events and timestamps.
**Fix:** Replace `DB::table()` calls with Eloquent equivalents where possible.

### 2.3 🟡 Three Models with `$guarded = []`
**Files:** `ExternalRegistration`, `ClubPartnership`, `ArticleTranslation`
**Risk:** Mass assignment — any attribute can be set via `fill()` or `create()`
While these models are only created internally (not from user input directly), it's a defense-in-depth gap.
**Fix:** Replace `$guarded = []` with explicit `$fillable` arrays.

### 2.4 🟡 Inline Validation in Controllers
Multiple controllers validate inline (`$request->validate([...])`) instead of using FormRequest classes. This is functional but inconsistent with Laravel conventions and harder to reuse/test.
**Affected:** EventController, ProfileController, DiveGroupController, ClassifiedController, CommentController, GdprController, and most Admin controllers.

### 2.5 🟡 OCR Temp Files — Incomplete Cleanup on Exception
**File:** `app/Services/BankReconciliationService.php`
If an exception occurs between image creation and the cleanup block (lines 89-116), temp PNG files in `/tmp/bank_img_*` will persist. The cleanup only runs on the happy path and the pdftoppm failure path.
**Fix:** Wrap in try/finally to ensure cleanup.

---

## PRIORITY 3 — Test Coverage Gaps

### 3.1 Current State
- **18 tests, 30 assertions** — covers basic registration, event registration, medical gate, GDPR export, admin access control, and public pages.
- **~258 routes** — vast majority untested.

### 3.2 Critical Untested Areas (by risk)
1. **Backup download/delete** — path traversal not tested
2. **Vote casting** — election mode, multi-position, token validation
3. **File uploads** — photo upload, PDF bank statement, UDDF import
4. **Payment reconciliation** — fuzzy matching, OCR pipeline
5. **Impersonation** — start/stop, session integrity
6. **Equipment CRUD** — loan management, maintenance scheduling
7. **Email sending** — template rendering, queue processing
8. **Calendar feed (iCal)** — output format, attendance count
9. **Social auth (OAuth)** — callback handling, account linking
10. **GDPR erasure** — anonymization completeness

### 3.3 Untested Admin Functionality
All 22 admin controllers have zero dedicated tests. The only admin test is `testNonAdminCannotAccessDashboard` and `testAdminCanAccessDashboard`.

---

## PRIORITY 4 — Configuration & Deployment

### 4.1 🟡 SESSION_SECURE_COOKIE Not Set
**File:** `config/session.php:172`
`'secure' => env('SESSION_SECURE_COOKIE')` — defaults to `null` (not enforced). On HTTPS deployments (Wasmer, Hetzner), session cookies should be secure-only.
**Fix:** Set `SESSION_SECURE_COOKIE=true` in production `.env` files.

### 4.2 🟡 CORS Allows All Methods
**File:** `config/cors.php`
`'allowed_methods' => ['*']` — overly permissive. The API only needs GET, POST, DELETE.
**Fix:** Restrict to `['GET', 'POST', 'DELETE']`.

### 4.3 ℹ️ DNS Misconfiguration (External)
The `clubcep.eu` domain has split-brain DNS: the authoritative nameservers at `topdns.com` haven't synced with the master at `heberg.ch` since April 2025. New DNS records (like `laravel.clubcep.eu`) won't resolve publicly until GreatHeberg fixes the zone transfer.

---

## PRIORITY 5 — Minor / Informational

### 5.1 Unescaped Blade Output — 29 Instances
Most are `json_encode()` for Chart.js data (safe) or admin badge HTML (low risk). The comment body (1.2) and classifieds body (1.7) are the real concerns.

### 5.2 No Authorization Policies
Only 11 `authorize` calls across all controllers. Most access control relies on route-level `role:bureau_master` middleware, which is coarse-grained. No Eloquent policies exist for fine-grained model-level authorization (e.g., "can this user edit this event?").

### 5.3 Calendar Feed — No Authentication
`CalendarFeedController::ical()` serves all events (non-cancelled) without any auth check. This is likely intentional (public iCal feed), but event descriptions and attendance counts are exposed to anyone with the URL.

### 5.4 `env()` Usage
Clean — no `env()` calls found outside config files. ✅

### 5.5 Shell Command Injection
`BankReconciliationService::ocrPdf()` properly uses `escapeshellarg()` on all user-supplied paths. ✅

### 5.6 Password Handling
User model uses `'password' => 'hashed'` cast (auto-hashing). Password hidden from serialization. ✅

### 5.7 Impersonation
Properly audit-logged with IP and user agent. Session stores original user ID for stop-impersonation. ✅

---

## Summary

| Priority | Count | Action Required |
|----------|-------|-----------------|
| P1 Security | 7 | Fix before production |
| P2 Quality | 5 | Fix in next sprint |
| P3 Test gaps | 10+ areas | Incremental coverage |
| P4 Config | 3 | Deploy config changes |
| P5 Info | 7 | Track / accept risk |

**Top 3 immediate fixes:**
1. `basename()` in BackupController (path traversal)
2. Throttle on forgot-password route
3. Escape comment body in Blade template
