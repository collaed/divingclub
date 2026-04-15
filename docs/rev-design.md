# DivingClub-Manager — Reverse-Engineered Design

Generated: 2026-04-15 from codebase analysis.

---

## 1. Primary Data Flows

### 1.1 Authentication Flow

```mermaid
sequenceDiagram
    participant B as Browser
    participant App as Laravel App
    participant RL as RateLimiter
    participant DB as Database
    participant EU as EU Login (ECAS)
    participant OAuth as OAuth Provider

    Note over B,DB: Standard Login
    B->>App: POST /login {email, password}
    App->>RL: tooManyAttempts(email|ip, 5)?
    alt Rate limited
        RL-->>App: true
        App-->>B: 422 "Too many attempts"
    else OK
        App->>DB: Auth::attempt(primary_email, password)
        alt Valid
            DB-->>App: User
            App->>RL: clear(key)
            App->>App: session()->regenerate()
            App-->>B: 302 → intended URL
        else Invalid
            App->>RL: hit(key, 600s)
            App-->>B: 302 → /login + errors
        end
    end

    Note over B,EU: EU Login (CAS 3.0)
    B->>App: GET /auth/eulogin/redirect
    App-->>B: 302 → ecas.ec.europa.eu/cas/login?service=callback_url
    B->>EU: Authenticate
    EU-->>B: 302 → callback_url?ticket=ST-xxx
    B->>App: GET /auth/eulogin/callback?ticket=ST-xxx
    App->>EU: GET /cas/laxValidate?ticket=ST-xxx&service=callback_url
    EU-->>App: XML {user, email, firstName, lastName}
    App->>DB: Find/create user, link social account
    App-->>B: 302 → /profile

    Note over B,OAuth: OAuth (Google/FB/MS/GH/LI)
    B->>App: GET /auth/{provider}/redirect
    App-->>B: 302 → provider OAuth URL
    B->>OAuth: Authorize
    OAuth-->>B: 302 → callback?code=xxx
    B->>App: GET /auth/{provider}/callback
    App->>OAuth: Exchange code for token + user info
    App->>DB: Match by social_id or email
    alt Linked account exists
        App-->>B: 302 → /profile (logged in)
    else Email matches existing
        App->>DB: Create UserSocialAccount link
        App-->>B: 302 → /profile (logged in)
    else No match
        App-->>B: 200 → choice page (link or register)
    end
```

### 1.2 Event Registration Flow

```mermaid
sequenceDiagram
    participant M as Member
    participant EC as EventController
    participant MCS as MedicalComplianceService
    participant DB as Database

    M->>EC: POST /events/{id}/register
    EC->>DB: Check duplicate registration
    alt Already registered
        EC-->>M: redirect + error "already registered"
    end

    EC->>EC: Check event_type
    alt pool/dive/training
        EC->>EC: Check hasDiveProfile()
        alt Profile incomplete
            EC-->>M: redirect + error "complete profile: {fields}"
        end
        EC->>MCS: isCompliant(user, event_date)
        alt Non-compliant
            EC->>EC: flash warning (still allows registration)
        end
    end

    EC->>DB: Check isFull()
    alt Full + waiting_list_enabled
        EC->>DB: Create registration(status=waiting, position=N)
    else Full + no waitlist
        EC-->>M: redirect + error "event full"
    else Not full
        EC->>DB: Create registration(status=confirmed)
        alt Event has deposits
            EC->>DB: Create PaymentExpected per deposit
        end
    end
    EC-->>M: redirect + success

    Note over M,DB: Cancellation with auto-promote
    M->>EC: POST /events/{id}/cancel-registration
    EC->>DB: Update registration status=cancelled
    EC->>DB: Delete pending PaymentExpected
    EC->>DB: Mark paid PaymentExpected refund_review_needed=true
    EC->>DB: Find first waiting registration
    alt Waiting exists
        EC->>DB: Update waiting → confirmed
    end
    EC-->>M: redirect + success
```

### 1.3 Medical Certificate Upload Flow

```mermaid
sequenceDiagram
    participant M as Member
    participant PDC as ProfileDocumentController
    participant MCS as MedicalComplianceService
    participant OCR as OcrMedicalCert Job
    participant DB as Database
    participant Disk as Private Storage

    M->>PDC: POST /profile/documents {file, cert_type, date}
    PDC->>PDC: Validate (mime, size ≤10MB)
    PDC->>Disk: Store as lastname-firstname-type-date.ext
    PDC->>DB: Create Document record
    PDC->>OCR: Dispatch OcrMedicalCert job (async)
    PDC->>MCS: evaluateCertificate(document)

    MCS->>DB: Get all active federation rules
    MCS->>MCS: Filter by user age brackets
    MCS->>MCS: Calculate min validity_months
    MCS->>MCS: Apply LIFRAS calendar rule (if applicable)
    MCS->>MCS: Apply FLASSA calendar rule (if applicable)
    MCS->>DB: Update document.expiry_date, compliance_notes
    MCS->>DB: Supersede previous current cert

    OCR->>OCR: Tesseract OCR on file
    OCR->>OCR: Extract date patterns
    OCR->>DB: Update document.date_established (if found)
```

### 1.4 Email Send Flow

```mermaid
sequenceDiagram
    participant Bureau as Bureau User
    participant EC as EmailController
    participant MB as MailBalancer
    participant R1 as Resend Primary
    participant R2 as Resend Secondary
    participant MJ as Mailjet (Postfix)
    participant DB as Database

    Bureau->>EC: POST /admin/email/send {template, recipients, body}
    EC->>MB: send(to, subject, body)
    MB->>MB: Check daily quotas (R1:98, R2:98, MJ:200)
    alt R1 has quota
        MB->>R1: Send via Resend API
        R1-->>MB: 200 + X-RateLimit-Remaining header
    else R2 has quota
        MB->>R2: Send via Resend API (secondary key)
    else MJ has quota
        MB->>MJ: Send via Postfix SMTP relay
    else All exhausted
        MB-->>EC: Error "daily limit reached"
    end
    MB->>DB: Log to email_logs
    EC-->>Bureau: redirect + success ":count emails queued"
```

### 1.5 Backup Flow

```mermaid
sequenceDiagram
    participant Cron as Scheduler
    participant Job as WeeklyBackup Job
    participant BS as BackupService
    participant Spatie as spatie/laravel-backup
    participant Disk as Local Storage
    participant SFTP as Offsite (ecb.pm)

    Cron->>Job: Sunday 03:00
    Job->>BS: create(includeFiles=true)
    BS->>Spatie: Artisan::call("backup:run")
    Spatie->>Spatie: Dump DB (pg_dump/mysqldump)
    Spatie->>Spatie: Archive storage/app/public + private
    Spatie->>Disk: Write .zip to storage/app/{AppName}/
    BS->>Disk: Move to storage/app/backups/backup-{timestamp}.zip
    BS->>SFTP: Upload dcms-bkp-{domain}-{date}.tar.gz
    Job->>BS: prune(keep=4)
    Job->>DB: ScheduleHeartbeat::beat('weekly-backup')
```

### 1.6 Vote Flow

```mermaid
sequenceDiagram
    participant Bureau as Bureau User
    participant VC as VoteController
    participant VPC as VotePublicController
    participant M as Member
    participant DB as Database

    Bureau->>VC: POST /admin/votes (create)
    VC->>DB: Create Vote + VoteOptions
    Bureau->>VC: POST /admin/votes/{id}/tokens
    VC->>DB: Create VoteToken per verified user (token=random 128 chars)

    Bureau->>VC: POST /admin/votes/{id}/open
    VC->>DB: Update status=open

    M->>VPC: GET /vote/{token}
    VPC->>DB: Find VoteToken, load Vote+Options
    VPC-->>M: Render ballot

    alt Simple mode
        M->>VPC: POST /vote/{token} {option_id}
        VPC->>DB: Upsert VoteBallot (token_hash=sha256(token))
        Note over VPC: Changeable if allow_change=true
    else Election mode
        M->>VPC: POST /vote/{token} {option_ids[]}
        VPC->>DB: Create VoteBallot(s) with token_hash=NULL (anonymous)
        VPC->>DB: Mark token consumed
        Note over VPC: Irreversible
    end
```

---

## 2. Type Definitions — Major Data Structures

### 2.1 User (core identity)
```
User {
    id: int (PK)
    username: string|null
    primary_email: string (unique)
    password: string|null (bcrypt)
    role_id: int (FK → roles)
    status_id: int (FK → member_statuses)
    preferred_locale: string|null
    email_verified_at: datetime|null
    cotisation_years: json|null  // ["2025","2026"]
    remember_token: string|null
    created_at, updated_at: datetime
    deleted_at: datetime|null (soft delete)
}
```

### 2.2 MemberDetail (46 fields)
```
MemberDetail {
    id: int (PK)
    user_id: int (FK → users, unique)
    first_name, last_name, birth_name: string|null
    sex: enum('M','F')|null
    date_of_birth: date|null
    place_of_birth, nationality: string|null
    phone_private, phone_office, phone_mobile: string|null
    address_line1, address_line2, city, postal_code, country: string|null
    emergency_contact_name, emergency_contact_phone, emergency_contact_relation: string|null
    blood_type: string|null
    allergies, medical_conditions: text|null
    avatar_path: string|null
    bcd_size, wetsuit_size, shoe_size, glove_size: string|null
    instructor_bio, instructor_specialties, instructor_motivation: text|null
    preferred_locale: string|null
    // + 15 more fields
}
```

### 2.3 Event (34 fields)
```
Event {
    id: int (PK)
    title: string
    color_hex: string|null
    event_type: enum('pool','dive','training','theory','social')
    event_date: date
    event_time, end_time: time|null
    end_date: date|null
    location: string|null
    description: text|null
    responsible_id, instructor_id: int|null (FK → users)
    assistant_ids: json|null
    max_participants: int|null
    waiting_list_enabled: bool (default false)
    inscription_open_at: datetime|null
    inscriptions_closed: bool (default false)
    estimated_cost: decimal|null
    deposit_1_date, deposit_2_date, deposit_3_date: date|null
    deposit_1_amount, deposit_2_amount, deposit_3_amount: decimal|null
    created_by: int (FK → users)
    status: enum('scheduled','cancelled','completed')
    is_federated: bool (default false)
    external_slots: int|null
    season_id: int|null (FK → seasons)
    dive_site_id: int|null (FK → dive_sites)
    whatsapp_group_url, participant_email: string|null
}
```

### 2.4 Document (20 fields)
```
Document {
    id: int (PK)
    user_id: int (FK → users)
    category: enum('medical','insurance','id','other')
    cert_type: string|null
    file_path: string
    original_filename: string
    mime_type: string
    size_bytes: int
    date_established: date|null
    expiry_date: date|null
    is_verified: bool (default false)
    verified_by: int|null (FK → users)
    verified_at: datetime|null
    superseded_by: int|null (FK → documents)
    is_current: bool (default true)
    is_compliant: bool|null
    compliance_notes: text|null
    reminder_30_sent_at, reminder_15_sent_at, reminder_7_sent_at, reminder_0_sent_at: datetime|null
    deleted_at: datetime|null (soft delete)
}
```

### 2.5 Equipment (23 fields)
```
Equipment {
    id: int (PK)
    club_id: string|null
    name, short_number: string
    brand, manufacturer: string|null
    type: string  // bcd, regulator, tank, wetsuit, fins, mask, computer, light, other
    serial_number: string|null
    purchase_date: date|null
    condition: string|null
    status: enum('available','on_loan','maintenance_required','retired')
    is_loanable: bool (default true)
    notes: text|null
    location: string|null
    // Tank-specific:
    threading, material: string|null
    manufacture_date: date|null
    weight_kg, volume: decimal|null
    test_pressure_bar, working_pressure_bar: decimal|null
    last_retest_date, next_retest_date, last_inventory_date: date|null
}
```

### 2.6 Federation API Contract
```
GET /api/federation/events
  Auth: X-Api-Key header (ClubPartnership.their_api_key_id)
  Response: [{id, title, event_date, event_type, location, max_participants, external_slots, registered_count}]

POST /api/federation/register
  Auth: X-Api-Key header
  Body: {event_id, first_name, last_name, email, federation, certification_level}
  Response: {id, status: "confirmed"|"waiting"}

GET /api/federation/register/{id}
  Response: {id, event_id, first_name, last_name, status}

DELETE /api/federation/register/{id}
  Response: 204
```

### 2.7 Instance Status API
```
GET /api/instance/status
  Auth: none
  Response: {name, version, members_count, events_count, uptime}
```

---

## 3. Physical File Structure → Component Map

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/           # 26 controllers — bureau-only CRUD
│   │   │   ├── DashboardController      → REQ-BAK-005 (worklist, stats)
│   │   │   ├── MemberController         → REQ-MEM-001..005, REQ-AUTH-005
│   │   │   ├── PaymentController        → REQ-PAY-001..004
│   │   │   ├── EquipmentController      → REQ-EQP-001..003
│   │   │   ├── NewsletterController     → REQ-MAIL-005
│   │   │   ├── VoteController           → REQ-VOTE-001..003
│   │   │   ├── SeasonController         → REQ-EVT-007
│   │   │   ├── BackupController         → REQ-BAK-001..002
│   │   │   ├── SettingsController       → REQ-THM-001, REQ-MEM-002
│   │   │   ├── RolePermissionController → REQ-AUTH-006
│   │   │   ├── EmailController          → REQ-MAIL-002
│   │   │   ├── EmailStatsController     → REQ-MAIL-003
│   │   │   ├── ArticleController        → REQ-CMS-001
│   │   │   ├── LibraryController        → REQ-DOC-001
│   │   │   ├── DiveSiteController       → REQ-SITE-001
│   │   │   ├── GuardianController       → REQ-MEM-009
│   │   │   ├── PartnershipController    → REQ-PART-001..002
│   │   │   ├── TrialRequestController   → REQ-TRIAL-001 (admin view)
│   │   │   ├── AuditLogController       → REQ-BAK-004
│   │   │   ├── AnnualReportController   → (annual stats export)
│   │   │   ├── AuditorController        → (read-only finance for auditors)
│   │   │   ├── DiveGroupRuleController  → REQ-EVT-009
│   │   │   ├── GuideController          → (in-app admin guide)
│   │   │   ├── LinkController           → (quick links management)
│   │   │   ├── MedicalExportController  → (CSV export for federations)
│   │   │   └── ThumbnailController      → (image thumbnail generation)
│   │   ├── Auth/            # 4 controllers
│   │   │   ├── LoginController          → REQ-AUTH-002
│   │   │   ├── RegisterController       → REQ-AUTH-001
│   │   │   ├── SocialAuthController     → REQ-AUTH-003
│   │   │   └── EuLoginController        → REQ-AUTH-004
│   │   ├── Api/             # 1 controller
│   │   │   └── FederationApiController  → REQ-PART-001
│   │   ├── EventController              → REQ-EVT-001..006
│   │   ├── DiveGroupController          → REQ-EVT-009
│   │   ├── InstructorAvailabilityController → REQ-EVT-010
│   │   ├── ProfileController            → REQ-MEM-001
│   │   ├── ProfileDocumentController    → REQ-MEM-005..007
│   │   ├── ProfileCertificationController → REQ-MEM-004
│   │   ├── ProfileAvatarController      → (avatar upload/crop)
│   │   ├── ProfileEmailController       → (secondary email management)
│   │   ├── GdprController              → REQ-MEM-008
│   │   ├── HomeController              → REQ-HOME-001..003
│   │   ├── HomepageLayoutController    → REQ-HOME-001 (widget drag-drop)
│   │   ├── VotePublicController        → REQ-VOTE-001..002
│   │   ├── ClassifiedController        → REQ-CMS-003
│   │   ├── CommentController           → REQ-CMS-004
│   │   ├── DocumentBrowserController   → REQ-DOC-002
│   │   ├── BuddyController            → REQ-BUD-001
│   │   ├── DuesCalculatorController    → REQ-PAY-001 (public calculator)
│   │   ├── CalendarFeedController      → REQ-CAL-001
│   │   ├── ContactController           → REQ-CONTACT-001
│   │   ├── ContactMemberController     → REQ-CONTACT-002
│   │   ├── QrCodeController            → REQ-PAY-003
│   │   ├── TrialController             → REQ-TRIAL-001
│   │   ├── MembersDirectoryController  → (member directory + trombinoscope)
│   │   ├── DiveDataController          → (UDDF export)
│   │   ├── PushSubscriptionController  → REQ-HOME-004
│   │   ├── InstallController           → (first-run setup wizard)
│   │   └── StagingMailController       → REQ-STG-002
│   ├── Middleware/          # 7 middleware
│   │   ├── CheckLicense     → REQ-BAK-003
│   │   ├── CheckRole        → REQ-AUTH-006
│   │   ├── EnsureEmailVerified → REQ-AUTH-007
│   │   ├── EnsureInstalled  → (redirect to /install if not set up)
│   │   ├── ParseEuropeanDates → (DD/MM/YYYY → Y-m-d conversion)
│   │   ├── SetLocale        → REQ-I18N-001
│   │   └── StagingBasicAuth → REQ-STG-001
│   └── Requests/           # 21 form request classes (all validation)
├── Models/                  # 56 Eloquent models, 118 relationships
├── Services/                # 22 services
│   ├── MedicalComplianceService    → REQ-MEM-006
│   ├── FeeCalculationService       → REQ-PAY-001
│   ├── BankReconciliationService   → REQ-PAY-002
│   ├── MailBalancer                → REQ-MAIL-001
│   ├── EmailStatsService           → REQ-MAIL-003
│   ├── InboundMailFilter           → REQ-MAIL-004
│   ├── NewsletterStencilSlicer     → REQ-MAIL-005
│   ├── BackupService               → REQ-BAK-001..002
│   ├── LicenseService              → REQ-BAK-003
│   ├── ThemeService                → REQ-THM-001
│   ├── ArticleTranslationService   → REQ-CMS-002
│   ├── DiveGroupProposalService    → REQ-EVT-009
│   ├── SwapSuggestionService       → REQ-EVT-009
│   ├── ImageQualityService         → REQ-EVT-008
│   ├── FaceDetectionService        → REQ-EVT-008
│   ├── PushNotificationService     → REQ-HOME-004
│   ├── UddfService                 → (UDDF dive log export)
│   ├── DanExportService            → (DAN insurance export)
│   ├── SocialPublishService        → (social media auto-publish)
│   ├── MailAliasService            → (email alias management)
│   ├── UpdateService               → (git pull + migrate from admin)
│   └── ScheduleHeartbeat           → REQ-BAK-005
├── Jobs/                    # 9 queued/scheduled jobs
│   ├── SendMedicalReminders        → daily 08:00
│   ├── WeeklyBackup                → Sunday 03:00
│   ├── ProcessTranslations         → hourly
│   ├── AutoOpenCloseVotes          → every minute
│   ├── PollInboundMail             → every minute
│   ├── PurgeAuditLogs              → monthly 1st 04:00
│   ├── CleanupClassifieds          → monthly 1st 05:00
│   ├── SendEquipmentReminders      → daily 09:00
│   └── OcrMedicalCert              → dispatched on upload
├── Helpers/
│   ├── HtmlSanitizer               → XSS prevention (HTMLPurifier)
│   └── IconHelper                  → Emoji icon directive
├── Traits/
│   └── Auditable                   → REQ-BAK-004
config/
├── app.php          → staging_mode, staging credentials
├── backup.php       → spatie/laravel-backup + offsite SFTP config
├── club.php         → CLUB_ID, CLUB_IBAN, CLUB_DOMAIN, FEDERATION_SALT
├── cotisation.php   → fee structure per status
├── languages.php    → 15 locale definitions
├── permission.php   → Spatie permission config
├── webpush.php      → VAPID keys for push notifications
database/
├── migrations/      → 66 migration files
├── seeders/         → DatabaseSeeder, CertificationLevelSeeder, FederationSeeder,
│                      MemberStatusSeeder, CepSeeder, DemoDataSeeder,
│                      JoomlaMemberImportSeeder, setup_permissions.php
resources/
├── views/           → 133+ Blade templates
│   ├── components/  → layout, admin-layout, admin-sidebar, form-enhancements,
│   │                  instant-search, photo-gallery, rich-editor, slideshow, auth-validation
│   ├── admin/       → 43 admin templates (all use admin-layout with sidebar)
│   ├── profile/     → show + 7 tab partials (info, private, diving, medical, registrations, renewal, documents)
│   ├── events/      → index, show, form, dive-groups, _participant_row
│   ├── auth/        → login, register, forgot-password, reset-password, verify-email
│   ├── errors/      → 403, 404, 419, 429, 500, 503 (AI-generated underwater animals)
│   ├── home*.blade  → home (widgets), home2 (dark), home3 (visual), home4 (tiles)
│   └── ...          → cms, vote, gdpr, classifieds, buddies, documents, etc.
├── scss/app.scss    → Bootstrap 5 + custom theme (CSS variables, dark mode, admin sidebar)
├── js/app.js        → Bootstrap JS + Chart.js via Vite
lang/                → 15 locale directories + vendor/backup translations
public/
├── sw.js            → Service worker (offline page + push notifications)
├── manifest.json    → PWA manifest
├── images/errors/   → 6 AI-generated WebP images (403-503)
tests/
├── Unit/            → 12 test files (models, services)
├── Feature/         → 10 test files (workflows, data integrity, migrations)
├── e2e/             → Playwright: test_ui.py (35), test_adversarial.py (55)
└── TESTING-REQUIREMENTS.md → traceability matrix
```

---

## 4. Technical Debt & TODOs

| # | Location | Type | Description | Impact |
|---|----------|------|-------------|--------|
| 1 | `AnnualReportController.php:73` | TODO | `departed` count hardcoded to 0 — needs member status change tracking | Low — annual report only |
| 2 | `MedicalComplianceService.php:61` | Hardcode | `$lifrasId = 2` — should query `Federation::where('acronym','LIFRAS')` | Medium — breaks if federation IDs change |
| 3 | `Auditable.php` | Design | Hand-rolled 40-line trait. No batch disable, no restore tracking, no URL logging | Medium — import/seeding triggers unwanted audit entries |
| 4 | `EuLoginController.php:42` | Security | `setNoCasServerValidation()` disables SSL cert verification on ticket validation | High — MITM risk on EU Login ticket validation |
| 5 | `BackupService.php` | Hybrid | Spatie engine wrapped in custom service for admin UI compatibility. Two code paths for .tar.gz (legacy) and .zip (new) | Low — works but could be simplified |
| 6 | `setup_permissions.php` | Not a migration | Permission matrix defined in a tinker script, not a seeder or migration. Must be run manually | Medium — easy to forget after fresh deploy |
| 7 | `Event.event_type` | No enum | Event types defined as string column with match() in model, not a DB enum or PHP enum | Low — works but no DB-level constraint |
| 8 | `MemberDetail` | 46 fields | Single table with 46 columns. Could benefit from JSON columns or separate tables for diving/instructor/emergency data | Low — works at current scale (101 members) |
| 9 | `InstructorAvailability` | No constraint | No unique constraint on (user_id, date, activity_type) — duplicate entries possible via race condition on AJAX toggle | Low — unlikely with single-user AJAX |
| 10 | `cotisation_years` on User | JSON column | Active status computed from JSON array. No index possible. Full table scan for "all active members" queries | Low at 101 members, would matter at 1000+ |

---

## 5. Dependency Graph (key packages)

```
laravel/framework v11        ← Core
├── spatie/laravel-permission v6  ← RBAC (7 roles, 18 permissions)
├── spatie/laravel-backup v9      ← Backup engine (NEW — replaced hand-rolled)
├── laravel/socialite v5          ← OAuth (Google, FB, MS, GH, LI)
├── laravel/horizon v5            ← Queue monitoring dashboard
├── apereo/phpcas v1.6            ← EU Login CAS 3.0 client
├── resend/resend-laravel v1.3    ← Email provider #1 and #2
├── intervention/image v4         ← Image processing (resize, quality)
├── barryvdh/laravel-dompdf v3    ← PDF generation (annual report)
├── endroid/qr-code v5            ← SEPA QR codes
├── ezyang/htmlpurifier v4        ← XSS prevention
└── minishlink/web-push v10       ← Push notifications (VAPID)
```
