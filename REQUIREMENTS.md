# Requirements — Architecture & Best Practices

Improvements identified from a codebase review against Laravel conventions and general software engineering best practices.

---

## High Priority

### REQ-01: Extract Form Request Classes

All controllers use inline `$request->validate()`. Create dedicated Form Request classes for every store/update action to centralize validation rules, custom messages, and authorization checks.

- **Affected controllers**: EventController, ProfileController, SettingsController, ArticleController, EquipmentController, EmailController, DiveSiteController, SeasonController, VoteController, ClassifiedController, PaymentController, RegisterController, and others.
- **Target directory**: `app/Http/Requests/`
- **Acceptance**: Zero inline `$request->validate()` calls remain in controllers.

### REQ-02: Introduce Authorization Policies

No `app/Policies/` exist. Authorization is scattered via `abort_unless()` and private helper methods (`authorizeBureau()`, `authorizeEventEdit()`). Create Laravel Policies for all models that require access control.

- **Models needing policies**: Event, Article, Equipment, DiveSite, Vote, LibraryFile, Document, Classified (Article), DiveGroup, EventPhoto, BuddyRequest.
- **Acceptance**: All authorization uses `$this->authorize()`, `Gate::allows()`, or `can` middleware. No manual `abort_unless()` for permission checks.

### REQ-03: Expand Test Coverage

Only 3 test files (18 tests, 30 assertions) for 258 routes and 63 tables. Add feature tests for all critical paths.

- **Untested areas requiring coverage**:
  - Admin dashboard, members management, impersonation
  - Article CRUD, translation, comments
  - Equipment inventory, loans, maintenance scheduling
  - Dive group creation, validation, proposal engine
  - Payment fee calculation, bank reconciliation
  - Backup create/inspect/download/delete
  - GDPR export and erasure
  - Vote creation, token generation, casting, results
  - Email template management and sending
  - Season patterns and event generation
  - Instructor availability toggle
  - Classified ads lifecycle (create, extend, expire)
  - Buddy request/response flow
  - Document upload, verification, medical compliance gate
  - QR code generation (SEPA, vCard, federation)
  - Calendar feed (iCal)
  - Trial request submission and admin management
  - OAuth login, account linking, dismissal
  - Dive data import/export (UDDF, DAN)
  - Settings CRUD (federations, statuses, medical rules, maintenance rules, theme, membership fees)
  - Library file management with visibility
  - Guardian linking and parental consent
  - Season management (holidays, patterns, preview, generate)
  - Club partnerships and federation API
- **Acceptance**: Every controller action has at least one happy-path and one failure-path test. Target >80% route coverage.

### REQ-04: Break Up Fat Controllers

Several controllers exceed 400 lines with 15+ methods. Split into focused single-responsibility controllers.

- **ProfileController** (409 lines, 18 methods) → `ProfileInfoController`, `ProfileAvatarController`, `ProfileCertificationController`, `ProfileDocumentController`, `ProfileEmailController`
- **EventController** (403 lines, 15 methods) → `EventController` (CRUD), `EventRegistrationController`, `EventPhotoController`
- **DiveGroupController** (401 lines, 14 methods) → `DiveGroupController` (CRUD), `DiveGroupProposalController`, `DiveGroupValidationController`
- **SettingsController** (221 lines, 17 methods) → `FederationSettingsController`, `MedicalRuleController`, `MaintenanceRuleController`, `ThemeController`, `MembershipFeeController`
- **Acceptance**: No controller exceeds 200 lines or 8 public methods.

### REQ-05: Remove Inline Closures from Routes

`web.php` (479 lines) contains inline closures for password reset, locale switching, cron endpoints, and email verification. These prevent `route:cache` from working.

- **Closures to extract**: locale switch, password reset (forgot + reset), email verification fulfill, email verification resend, cron endpoints (4), cotisation view, contact view, admin send-reset.
- **Acceptance**: `web.php` contains zero `function ()` closures. `php artisan route:cache` succeeds.

---

## Medium Priority

### REQ-06: Extract Business Logic from Controllers into Services/Actions

The `EventController::register()` method (60+ lines) handles medical compliance, payment generation, waiting list logic, and communication string building. Similar patterns exist in other controllers.

- **Candidates for extraction**:
  - Event registration → `EventRegistrationService` or `RegisterForEvent` action
  - Event cancellation with refund flagging → `CancelEventRegistration` action
  - Photo upload with quality scoring and social publishing → `EventPhotoService`
  - Bank statement import and matching → already in `BankReconciliationService` but controller still orchestrates too much
- **Acceptance**: Controller methods are ≤30 lines, delegating to services/actions.

### REQ-07: Create Model Factories

Only `UserFactory` exists. Create factories for all models used in tests.

- **Models needing factories**: Event, Article, Equipment, DiveSite, DiveGroup, Vote, VoteOption, VoteToken, EmailTemplate, LibraryFile, Document, MemberDetail, MemberLicence, Federation, CertificationLevel, Season, SeasonPattern, BuddyRequest, Classified (Article), EventRegistration, PaymentExpected, BankTransaction, EquipmentLoan, EquipmentMaintenance, TrialRequest, ParentalConsent, GuardianLink, ClubPartnership, ExternalRegistration, ArticleTranslation, EventPhoto, MemberStatus, Role.
- **Acceptance**: Every model referenced in tests uses its factory. No manual `Model::create()` with hardcoded attributes in tests.

### REQ-08: Add Rate Limiting to Sensitive Endpoints

Only login has `throttle:5,1`. Other sensitive endpoints lack rate limiting.

- **Endpoints to protect**: trial request submission, contact form, GDPR export, GDPR erasure, email sending, password reset request, vote casting, photo upload, bank statement import.
- **Acceptance**: All public-facing and destructive endpoints have appropriate throttle middleware.

### REQ-09: Secure Cron Endpoints

Cron triggers (`/cron/run`, `/cron/run-schedule`, `/cron/medical-reminders`, `/cron/weekly-backup`) use GET with a query string key. GET parameters leak into logs, referrer headers, and browser history.

- **Options**:
  - Switch to POST with an `Authorization` header or `X-Cron-Key` header
  - Or remove web-based cron entirely and rely on server-side `schedule:run`
- **Acceptance**: No secrets appear in URL query strings.

### REQ-10: Consistent Authorization Pattern

Routes mix `middleware('role:bureau_master')` with in-controller `$this->authorizeBureau()` checks. Standardize on one approach.

- **Recommendation**: Use middleware for role gating at the route level; use Policies (REQ-02) for model-level authorization.
- **Acceptance**: No private `authorize*()` helper methods in controllers.

---

## Low Priority

### REQ-11: Add API Versioning

`api.php` has routes with no `/v1/` prefix or versioning strategy.

- **Acceptance**: All API routes are prefixed with `/v1/` and a strategy exists for future versions.

### REQ-12: Replace Static ThemeSetting Access with Service

`ThemeSetting::set()` / `ThemeSetting::get()` are static methods acting as a global key-value store. Wrap in an injectable `ThemeSettingService` for testability.

- **Acceptance**: No static `ThemeSetting::get()` / `ThemeSetting::set()` calls outside the service class.

### REQ-13: Scope License Watermark View Composer

The `licenseWatermark` view composer runs on `*` (every view including partials). Scope it to the layout template only.

- **Acceptance**: View composer targets `components.layout` (or equivalent) instead of `*`.

### REQ-14: Consistent DB Transaction Usage

`EventController::register()` uses `DB::transaction()` but other multi-step operations (payment reconciliation, GDPR erasure, equipment loan/return, bulk event generation) may not.

- **Acceptance**: All multi-model write operations are wrapped in database transactions.
