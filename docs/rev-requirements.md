# DivingClub-Manager — Reverse-Engineered Requirements

Generated: 2026-05-23 from codebase analysis of 56 models, 331 routes, 27 services, 74 migrations.

---

## 1. Authentication & Authorization

### REQ-AUTH-001: User Registration
**EARS:** When a visitor submits the registration form with valid first_name, last_name, email, password, and passes the honeypot+timestamp CAPTCHA, the system shall create a member account with role `member`, send a verification email, and redirect to the login page.
- **Edge cases:** Duplicate email → validation error. Honeypot field filled → silent reject. Timestamp < 3 seconds → reject (bot). Password < 8 chars → validation error.
- **Security:** Password hashed via bcrypt. Email verification required before profile access (EnsureEmailVerified middleware).

### REQ-AUTH-002: Login with Rate Limiting
**EARS:** When a user submits valid credentials, the system shall authenticate, regenerate the session, and redirect to the intended URL. While the user has exceeded 5 failed attempts within 10 minutes for the same email+IP combination, the system shall reject login attempts with a countdown message.
- **Edge cases:** Invalid email format → validation error. Correct email, wrong password → increment rate limiter. Rate limit key = `transliterate(lowercase(email))|ip`.
- **Security:** Uses Laravel `RateLimiter` facade with 600-second decay. Session regenerated on login. CSRF token required.

### REQ-AUTH-003: OAuth Social Login (5 providers)
**EARS:** When a user clicks a social login button (Google, Facebook, Microsoft, GitHub, LinkedIn), the system shall redirect to the provider, receive the callback, and either: (a) log in if a linked account exists, (b) link to existing account if email matches, or (c) present a "link or register" choice page if email doesn't match.
- **Edge cases:** Provider returns no email → error. Email matches but different provider → choice page. User cancels OAuth → redirect to login.
- **Security:** OAuth state parameter validated. Provider tokens not stored.

### REQ-AUTH-004: EU Login (ECAS/CAS 3.0)
**EARS:** When a user clicks the EU Login button, the system shall redirect to `ecas.ec.europa.eu/cas/login` with the callback URL as service parameter. Upon return with a valid ticket, the system shall validate via `laxValidate` and authenticate the user.
- **Edge cases:** Service URL not registered → EU Login rejects with INVALID_SERVICE. Ticket expired → re-authenticate. User not in system → auto-create with `member` role.
- **Security:** Ticket validated server-side via HTTPS. `laxValidate` accepts external/self-registered users.

### REQ-AUTH-005: Impersonation
**EARS:** When a bureau user clicks "Impersonate" on a member profile, the system shall store the original user ID in session, log in as the target user, and display an impersonation banner. When the bureau user clicks "Stop", the system shall restore the original identity.
- **Edge cases:** Non-bureau user attempts impersonation → 403. Stop without active impersonation → 403.
- **Security:** Impersonation start/stop logged in audit_logs with IP and user agent.

### REQ-AUTH-006: Role-Based Access Control
**EARS:** The system shall enforce access control via 7 Spatie roles and 18 permissions:

| Role | Permissions |
|------|------------|
| bureau_master | All 18 |
| bureau_technical | 13 (no payments, backups, roles, settings, impersonation) |
| bureau_finance | 6 (members, payments, email, stats, audit, documents) |
| instructor | 3 (events, documents, dive sites) |
| instructor_apnea | Same as instructor |
| auditor | Read-only financial view |
| member | No admin permissions |
| public | No permissions |

- **Security:** `CheckRole` middleware on all `/admin/*` routes. Individual `abort_unless(can())` checks in 17 controller methods.

### REQ-AUTH-007: Email Verification
**EARS:** Until a user has verified their email address, the system shall block access to all authenticated routes except logout and the verification notice page.
- **Middleware:** `EnsureEmailVerified` applied to all authenticated routes.

---

## 2. Member Management

### REQ-MEM-001: Member Profile (46 fields)
**EARS:** When a member views their profile, the system shall display personal info, diving info, medical status, licences, certifications, documents, registrations, and renewal info in tabbed layout.
- **Fields:** first_name, last_name, birth_name, sex, date_of_birth, place_of_birth, nationality, phone_private, phone_office, phone_mobile, address_line1, address_line2, city, postal_code, country, emergency_contact_name, emergency_contact_phone, emergency_contact_relation, blood_type, allergies, medical_conditions, avatar_path, preferred_locale, bcd_size, wetsuit_size, shoe_size, glove_size, instructor_bio, instructor_specialties, instructor_motivation, + 15 more.
- **Privacy:** Regular members see only name+avatar of others. Bureau sees all. Instructors see limited info only for their event participants (scoped via `view event participants` permission + event date check).

### REQ-MEM-002: Member Statuses (8 types)
**EARS:** Each member shall have exactly one status from: membre_de_droit, actif, fonctionnaire, honoraire, junior, famille, externe, associe, assimile, sympathisant.
- **Business rule:** Status determines fee calculation base amount.

### REQ-MEM-003: Multi-Federation Licences
**EARS:** When a bureau user edits a member's licence, the system shall store federation_id, licence_number, licence_request_date, licence_request_pending, insurance_type, medical_cert_expiry, season, and registration_date.
- **Federations:** 11 active — FFESSM, LIFRAS, FLASSA, NELOS, VDST, PADI, SSI, UCPA, BSAC, NASDS, CMAS.
- **Edge cases:** Inline editing with datepicker + Today button. Pending status toggleable.

### REQ-MEM-004: Certification Levels (105 levels)
**EARS:** Each member may hold multiple certifications across federations, with one marked as primary. Cross-federation equivalence groups enable comparison (e.g., FFESSM N1 ≈ PADI OWD ≈ CMAS 1★).

### REQ-MEM-005: Profile Documents
**EARS:** When a member uploads a document (medical cert, ID, insurance), the system shall rename it to `lastname-firstname-type-date.ext`, store in private disk, and trigger OCR for medical certs.
- **Edge cases:** Duplicate upload → supersedes previous. File > 10MB → validation error. Non-PDF/image → validation error.
- **Security:** Documents stored in `storage/app/private/` (not public). Access requires authentication + ownership or bureau role.

### REQ-MEM-006: Medical Compliance
**EARS:** The system shall evaluate medical certificates against ALL active federation rules (not just the member's own federation). The most restrictive rule determines expiry.

| Federation | Rule |
|-----------|------|
| FFESSM | Issue date + validity_months (age-bracketed) |
| LIFRAS | Jan-Aug → Jan 31 Y+1; Sep-Dec → Jan 31 Y+2 |
| FLASSA | Age 18-45: Jan-Aug → Dec 31 Y+1, Sep-Dec → Dec 31 Y+2. Others: Jan-Aug → Dec 31 Y, Sep-Dec → Dec 31 Y+1 |

- **Status levels:** compliant (>30d), expiring (≤30d, warning), expired (danger), missing (danger).
- **Automated reminders:** Sent at 30, 15, 7, and 0 days before expiry via `SendMedicalReminders` job (daily 08:00).

### REQ-MEM-007: OCR on Medical Certificate Upload
**EARS:** When a medical certificate is uploaded, the system shall dispatch an `OcrMedicalCert` job that runs Tesseract OCR, extracts date patterns, and auto-fills `date_established`.

### REQ-MEM-008: GDPR Compliance
**EARS:** When a member requests data export, the system shall return a JSON file containing: user, detail, emails, licences, documents (metadata only), consents, event_registrations, payments. When a member confirms erasure with password, the system shall: delete all documents from disk, anonymize all personal fields to 'ERASED'/null, replace email with `erased-{id}@erased.local`, delete social accounts, and log the erasure in audit_logs.
- **Edge cases:** Wrong password → validation error. Event registrations preserved (anonymized user, history intact).

### REQ-MEM-009: Minors & Guardians
**EARS:** When a member is under 18, the system shall require a guardian link and parental consent before allowing event registration.
- **Models:** GuardianLink (many-to-many users), ParentalConsent (per-minor, per-activity-type).

### REQ-MEM-010: isActive() Computation
**EARS:** A member is active when their `cotisation_years` JSON array contains the current season year (Sep-Dec = next year, Jan-Aug = current year).

---

## 3. Events & Calendar

### REQ-EVT-001: Event Types
**EARS:** Events shall be categorized as: pool (#0077be), dive (#003366), training (#28a745), theory (#6f42c1), social (#ffc107).

### REQ-EVT-002: Event Registration with Capacity
**EARS:** When a member registers for an event that is not full, the system shall create a confirmed registration. When the event is full and waiting_list_enabled is true, the system shall create a waiting registration with sequential position. When the event is full and waiting_list_enabled is false, the system shall reject with error.
- **Edge cases:** Duplicate registration → error. Re-registration after cancel → allowed (old cancelled record deleted first).

### REQ-EVT-003: Waiting List Auto-Promotion
**EARS:** When a confirmed registration is cancelled, the system shall automatically promote the first waiting-list member to confirmed status.

### REQ-EVT-004: Medical Gate
**EARS:** When a member registers for a pool, dive, or training event, the system shall check medical compliance at the event date. If non-compliant, the system shall display a warning but still allow registration. Social events skip the medical gate entirely.
- **Edge cases:** Profile incomplete (missing DOB, sex, mobile, emergency contact) → blocked with specific field list.

### REQ-EVT-005: Deposit-Based Payments
**EARS:** When an event has deposit_1_amount/date (up to 3 deposits), the system shall auto-create PaymentExpected records upon registration. When registration is cancelled, pending payments are deleted but paid payments are preserved with `refund_review_needed=true`.

### REQ-EVT-006: Event Calendar Views
**EARS:** The system shall display events in month, week, and day views with color-coded event types.

### REQ-EVT-007: Season Patterns
**EARS:** When a bureau user generates events from season patterns, the system shall create recurring events based on day_of_week, start_time, title, event_type, location, max_participants, with holiday exclusions.
- **Fields:** description, estimated_cost, registration_closes_days_before, dive_site_id.

### REQ-EVT-008: Event Photos
**EARS:** When a member uploads photos to an event, the system shall store them with quality_score (via ImageQualityService), has_faces flag (via FaceDetectionService), and GDPR compliance (photos with faces hidden from public).

### REQ-EVT-009: Dive Groups (Trello-style)
**EARS:** When an instructor creates dive groups for an event, the system shall support drag-and-drop member assignment, leader designation, and 14 buddy rules (via DiveGroupRule model). The system shall offer AI-generated group proposals via DiveGroupProposalService and swap suggestions via SwapSuggestionService.

### REQ-EVT-010: Instructor Calendar
**EARS:** When an instructor marks availability on the weekly grid, the system shall create an InstructorAvailability record with activity_type (10 types: pool, kids, apnea, quarry, theory, etc.) and auto-register for matching events. All members can view (read-only); instructors can edit.
- **Edge cases:** Invalid month parameter → falls back to current month.

---

## 4. Payments & Finance

### REQ-PAY-001: Fee Calculation
**EARS:** When calculating membership fees, the system shall apply: base amount per status × age discount + optional components (e.g., Licence FLASSA €10). The calculation is deterministic (same inputs → same output).
- **Service:** FeeCalculationService.calculate(user, seasonYear, selectedOptionalSlugs[])

### REQ-PAY-002: Bank Reconciliation
**EARS:** When a bureau user pastes bank statement lines, the system shall fuzzy-match against expected payments using communication codes and member names.
- **Service:** BankReconciliationService

### REQ-PAY-003: SEPA QR Codes
**EARS:** When a member views their payment, the system shall generate a SEPA QR code (EPC format) for quick bank transfer via QrCodeController.

### REQ-PAY-004: Cotisation Generation
**EARS:** When a bureau user triggers fee generation for a season, the system shall create MembershipFee records for all active members.

---

## 5. Equipment

### REQ-EQP-001: Equipment Inventory (23 fields)
**EARS:** Each equipment item shall track: name, short_number, brand, manufacturer, type, serial_number, purchase_date, condition, status (available/on_loan/maintenance_required/retired), is_loanable, location, + tank-specific fields (threading, volume, material, pressures, retest dates).

### REQ-EQP-002: Loan Workflow
**EARS:** When a bureau user loans equipment, the system shall verify availability, create an EquipmentLoan record, and set status to on_loan. When returned, the system shall check for overdue maintenance and set status accordingly.
- **Edge cases:** Loan unavailable equipment → error. Quick-loan: multiple items to one member in one action.

### REQ-EQP-003: Maintenance Scheduling
**EARS:** When maintenance is completed, the system shall auto-schedule the next maintenance based on EquipmentMaintenanceRule interval_months.
- **Reminders:** SendEquipmentReminders job runs daily at 09:00.

---

## 6. Email & Newsletters

### REQ-MAIL-001: 3-Provider Load Balancing
**EARS:** The system shall distribute outbound email across 3 providers: Resend primary (98/day), Resend secondary (98/day), Mailjet (200/day) = 396/day total. Provider selection rotates based on remaining daily quota.
- **Service:** MailBalancer

### REQ-MAIL-002: Email Templates with Variables
**EARS:** When a bureau user sends email, the system shall support templates with variable substitution and 6 target groups.

### REQ-MAIL-003: Email Delivery Stats
**EARS:** The system shall display per-recipient delivery tracking from Mailjet + Resend APIs with date navigation.
- **Edge cases:** Invalid date parameter → falls back to today.

### REQ-MAIL-004: Inbound Mail Filtering
**EARS:** When processing inbound email, the system shall strip signatures, quoted replies, and disclaimers, then optionally moderate via 1min.ai (gpt-4o-mini).
- **Service:** InboundMailFilter

### REQ-MAIL-005: Newsletter Workflow
**EARS:** Newsletters follow: draft → submit for approval → 3 bureau approvals required → send to all verified members. Test send available at any stage.
- **Stencil:** NewsletterStencilSlicer accepts 1200×1860 image, extracts 5 components.

---

## 7. Voting

### REQ-VOTE-001: Simple Votes
**EARS:** When a bureau user creates a simple vote, the system shall generate tokens for all verified members. Members vote via token URL. Votes are changeable (unless allow_change=false). Token hash stored with ballot for change tracking.

### REQ-VOTE-002: Election Votes
**EARS:** When a bureau user creates an election vote, ballots are anonymous (token_hash=null), irreversible (token marked consumed), and support multi-position selection (num_positions).
- **Edge cases:** Already consumed token → error. Closed vote → error.

### REQ-VOTE-003: Auto Open/Close
**EARS:** The AutoOpenCloseVotes job runs every minute and transitions vote status based on scheduled open/close dates.

---

## 8. CMS & Articles

### REQ-CMS-001: 13 Article Types
**EARS:** Articles are categorized as: news, history, safety, training, regulation, trip_report, trip_proposal, environment, gear, classified, faq, newsletter, video.

### REQ-CMS-002: Article Translations
**EARS:** When an article is published, the ProcessTranslations job (hourly) auto-translates to all 15 locales via Google Translate API. Translations stored in article_translations table for instant display.
- **Edge cases:** Original modified after translation → "outdated" warning shown.

### REQ-CMS-003: Classifieds with Auto-Expiry
**EARS:** When a member posts a classified, the system shall set expires_at to 30 days. The CleanupClassifieds job (monthly) soft-deletes expired classifieds.
- **Security:** Only the author can edit/delete their classified (abort 403 for others).

### REQ-CMS-004: Threaded Comments
**EARS:** Authenticated members can comment on articles. Comments support threading (parent_id). Authors and bureau can delete.

---

## 9. Documents & Library

### REQ-DOC-001: Bureau Document Library
**EARS:** The system shall provide a file browser with folder organization, upload, rename, delete, and visibility control (public/members/instructors/bureau).

### REQ-DOC-002: Member Document Browser
**EARS:** Members can browse documents visible to their role level. Folder navigation with breadcrumbs, thumbnail previews for images.

---

## 10. Dive Sites

### REQ-SITE-001: Dive Site Database
**EARS:** Each dive site stores: name, latitude, longitude, max_depth, conditions, marine_life, safety_notes, access_notes, facilities, + weather integration via Open-Meteo API.

---

## 11. Buddy System

### REQ-BUD-001: Buddy Requests
**EARS:** Members can post buddy requests with description, preferred dates, and experience level. Other members can respond.

---

## 12. Backup & System

### REQ-BAK-001: Automated Backup
**EARS:** The WeeklyBackup job (Sunday 03:00) creates a full backup (DB + storage files) via spatie/laravel-backup, uploads to offsite SFTP, and retains per cleanup strategy (7d all, 30d daily, 8w weekly, 4m monthly).

### REQ-BAK-002: Admin Backup UI
**EARS:** Bureau users can create, inspect (manifest with table row counts), download, and delete backups from the admin interface. Supports both legacy .tar.gz and new .zip formats.

### REQ-BAK-003: License System
**EARS:** The system shall verify RSA-2048 signed license keys. Free tier allows up to 100 members. Expired or invalid licenses block new registrations but don't lock out existing users.
- **Middleware:** CheckLicense runs on registration routes.

### REQ-BAK-004: Audit Logging
**EARS:** The Auditable trait logs all create/update/delete operations on models with: user_id, impersonated_user_id, action, model_type, model_id, old_values, new_values, ip_address, user_agent.
- **Retention:** PurgeAuditLogs job runs monthly on the 1st at 04:00.

### REQ-BAK-005: Schedule Heartbeats
**EARS:** All 8 scheduled jobs report heartbeats to the schedule_heartbeats table after execution. The admin dashboard displays last-run times and alerts for stale heartbeats.

---

## 13. Internationalization

### REQ-I18N-001: 15 Locales
**EARS:** The system shall support: en, fr, de, lb, pt, it, nl, es, pl, hu, ro, el, et, sk, fi. User preference stored in member_details.preferred_locale. Browser detection for guests.
- **Middleware:** SetLocale applied to all web routes.
- **Constraint:** Portuguese must be European Portuguese (pt-PT), not Brazilian.

---

## 14. Theme & Appearance

### REQ-THM-001: 6 Theme Presets
**EARS:** The system shall offer: Ocean (#003366), Coral (#c0392b), Lagoon (#00695c), Abyss (#1a237e), Tropical (#00838f), Arctic (#37474f). Custom colors configurable via admin.

### REQ-THM-002: Dark Mode
**EARS:** Users can toggle dark mode via header button. Preference persisted in localStorage.

### REQ-THM-003: Font Size Adjustment
**EARS:** Users can increase/decrease font size (80%-130%) via header buttons. Persisted in localStorage.

---

## 15. Homepage & PWA

### REQ-HOME-001: Configurable Widget Layout
**EARS:** The homepage supports drag-and-drop widget arrangement: hero slideshow, articles, upcoming events, photo gallery, quick links, custom HTML. Per-widget visibility (public/members/instructors/bureau).

### REQ-HOME-002: Visual Landing Page (home3)
**EARS:** Public landing page with: hero cycling photos, slide-in login panel, numbers strip, photo mosaic with fullscreen gallery, events by category, value cards, team faces.

### REQ-HOME-003: Tile Dashboard (home4)
**EARS:** Authenticated dashboard with role-based quick action tiles, "My Upcoming Dives", "Recent Articles" teasers.

### REQ-HOME-004: PWA
**EARS:** The system shall be installable as a PWA with offline page, service worker (cache-first for navigation), and push notification support.

---

## 16. Inter-Club Partnerships

### REQ-PART-001: Federation API
**EARS:** The system shall expose REST endpoints for partner clubs: GET /api/federation/events (list federated events), POST /api/federation/register (register external member), GET/DELETE /api/federation/register/{id}.
- **Security:** API key authentication via ClubPartnership model (api_secret_hash).

### REQ-PART-002: External Registrations
**EARS:** Partner clubs can register their members for federated events. External registrations tracked separately in ExternalRegistration model.

---

## 17. Trial Requests

### REQ-TRIAL-001: Public Trial Page
**EARS:** When a visitor submits a trial request with first_name, last_name, email, phone, message, the system shall create a TrialRequest record. Honeypot field + timestamp check for bot prevention.
- **Edge cases:** Honeypot filled → silent reject. Submit < 3 seconds → error.

---

## 18. Contact

### REQ-CONTACT-001: Contact Form
**EARS:** Authenticated members can send messages to the bureau via the contact form. Messages sent via MailBalancer.

### REQ-CONTACT-002: Contact Member
**EARS:** Members can contact other members without seeing their email. Messages routed through the system.

---

## 19. Calendar Feed

### REQ-CAL-001: iCal Export
**EARS:** The system shall provide an iCal (.ics) feed of events via CalendarFeedController.

---

## 20. Staging & Development

### REQ-STG-001: Staging Mode
**EARS:** While STAGING_MODE=true, the system shall: redirect all outbound email to MAIL_ALWAYS_TO, display a staging banner with mailbox link, and require basic auth (StagingBasicAuth middleware).

### REQ-STG-002: Staging Mailbox
**EARS:** The staging mailbox (StagingMailController) captures and displays all outbound email for testing.

---

## Security Constraints Summary

| Constraint | Implementation |
|-----------|---------------|
| CSRF protection | All POST/PUT/DELETE forms require `_token` |
| XSS prevention | HTMLPurifier via HtmlSanitizer (3 presets: rich, basic, comment) |
| SQL injection | Eloquent ORM + parameterized queries throughout |
| Rate limiting | Laravel RateLimiter on login (5 attempts/10min per email+IP) |
| File upload security | Validation on mime type + size. Private storage for medical docs |
| Session security | Regenerated on login. HttpOnly + Secure cookies |
| Password security | Bcrypt hashing. Minimum 8 characters |
| Privacy | Profile fields scoped by viewer role. Documents in private storage |
| Audit trail | All model changes logged with user, IP, old/new values |
| License enforcement | RSA-2048 signature verification. Registration blocked when invalid |
| Email security | TLS transport. 3-provider rotation prevents single-point failure |
| GDPR | Export + erasure + consent management. Anonymization preserves referential integrity |

---

## Technical Debt

| Location | Issue |
|----------|-------|
| `AnnualReportController.php:73` | `TODO: track departures when member status tracking is added` |
| `MedicalComplianceService.php:61` | Hardcoded `$lifrasId = 2` — should query by acronym |
| `Auditable.php` | Hand-rolled trait, no batch support, no disable mechanism for imports |
| `BackupService.php` | Hybrid: spatie engine + custom admin UI wrapper — could be simplified |
| `EuLoginController.php:42` | `setNoCasServerValidation()` disables SSL cert verification |
