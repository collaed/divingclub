# DivingClub-Manager — Technical Documentation

## 1. Architecture Overview

DivingClub-Manager is a server-rendered monolith built on Laravel 12 (PHP 8.3). It follows standard Laravel conventions with minimal custom abstractions: Eloquent ORM for data access, Blade templates for rendering, and Bootstrap 5 for the UI. There is no SPA frontend — all interactivity is achieved through Blade, vanilla JavaScript, and AJAX where needed.

### 1.1 Design Principles

- **Convention over configuration** — follows Laravel defaults wherever possible
- **Database-agnostic** — all queries work on MySQL, PostgreSQL, and SQLite
- **No external JS frameworks** — vanilla JS + Bootstrap 5, no React/Vue/Alpine
- **Multi-language first** — all user-facing strings go through `__()` / `trans()`
- **Multi-club ready** — club identity is stored in `theme_settings`, never hardcoded

### 1.2 Technology Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Language | PHP | 8.3 |
| Framework | Laravel | 12.x |
| Database | MySQL 8+ / PostgreSQL 14+ / SQLite | |
| Frontend | Bootstrap 5, Sass, vanilla JS | 5.3 |
| Build | Vite + laravel-vite-plugin | 6.x |
| Charts | Chart.js | 4.x |
| PDF | barryvdh/laravel-dompdf | 3.x |
| QR Codes | endroid/qr-code | 5.x |
| HTML Sanitization | ezyang/htmlpurifier | 4.x |
| Push Notifications | minishlink/web-push | 10.x |
| Permissions | spatie/laravel-permission | 6.x |
| OAuth | Laravel Socialite | 5.x |
| CAS/EU Login | apereo/phpcas | 1.6 |
| Code Style | Laravel Pint | 1.x |
| Static Analysis | PHPStan (Larastan) | Level 6 |
| Testing | PHPUnit | 11.x |

### 1.3 Required System Components

- **PHP 8.3+** with extensions: `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `gd` or `imagick`
- **Composer 2.x** for PHP dependency management
- **Node.js 18+** and **npm** for frontend asset compilation
- **A database**: MySQL 8+, PostgreSQL 14+, or SQLite 3
- **A web server**: Caddy (recommended), Nginx + PHP-FPM, or Apache
- **A mail server or SMTP relay** (Resend, Mailjet, or Postfix)
- **Cron** — one entry: `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`
- **Queue worker** (optional but recommended): `php artisan queue:work`

---

## 2. Directory Structure

```
app/                          # Application code
├── Auth/                     # Custom user provider
├── Console/Commands/         # Artisan commands (auto-registered)
├── Enums/                    # PHP 8.1 enums
├── Helpers/                  # HtmlSanitizer, etc.
├── Http/
│   ├── Controllers/          # 62 controllers
│   │   ├── Admin/            # 26 admin controllers
│   │   ├── Auth/             # 4 auth controllers (Login, Register, Social, EuLogin)
│   │   ├── Api/              # 1 API controller (FederationApi)
│   │   └── Concerns/         # Traits (PaginatesFromRequest)
│   └── Middleware/           # 7 middleware classes
├── Jobs/                     # 9 queued/scheduled jobs
├── Models/                   # 58 Eloquent models
├── Providers/                # Service providers
├── Services/                 # 28 service classes (incl. Homogeneity/)
└── Traits/                   # Auditable, etc.
bootstrap/
├── app.php                   # Middleware, routing, exception config
└── providers.php             # Service provider registration
config/                       # 18 config files
database/
├── migrations/               # 78 migration files
├── seeders/                  # DatabaseSeeder, SampleDataSeeder, CertificationSeeder, EquipmentSeeder
└── factories/                # Model factories
lang/                         # 15 locale directories + JSON files
resources/
├── views/                    # 157 Blade templates (22 subdirectories)
├── scss/                     # 11 SCSS partials + app.scss
├── js/                       # app.js, table-utils.js, etc.
└── css/                      # Base CSS (Vite entry)
routes/
├── web.php                   # Public + authenticated routes (~180 routes)
├── admin.php                 # Bureau admin routes (~160 routes)
├── api.php                   # API routes (federation endpoints)
└── console.php               # Scheduled tasks
tests/
├── Feature/                  # 23 feature test files
├── Unit/                     # 15 unit test files
└── e2e/                      # 7 end-to-end test files
```

**Totals**: 341 routes, 58 models, 157 Blade templates, 28 services, 78 migrations, 38 test files (253 tests, 598 assertions).

---

## 3. Database Schema Overview

The application uses **131 tables** organized into the following domains:

### Core / Members
`users`, `member_details`, `user_emails`, `user_social_accounts`, `member_statuses`, `member_licences`, `guardian_links`, `parental_consents`, `gdpr_consents`, `user_certification_levels`, `certification_levels`, `federations`

### Auth & Permissions (Spatie)
`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`, `legacy_roles`, `sessions`, `password_reset_tokens`, `failed_login_attempts`

### Events & Registration
`events`, `event_registrations`, `external_registrations`, `seasons`, `season_patterns`, `season_holidays`, `instructor_availabilities`

### Dive Planning
`dive_groups`, `dive_group_members`, `dive_group_rules`, `dive_sites`

### Trip Settlement
`trip_participants`, `trip_receipts`

### Equipment
`equipment`, `equipment_loans`, `equipment_maintenance`, `equipment_maintenance_rules`

### Payments & Finance
`payment_expected`, `bank_transactions`, `membership_fees`, `membership_fee_components`

### Content & CMS
`articles`, `article_translations`, `article_images`, `article_comments`, `links`, `library_files`, `documents`

### Communication
`email_log`, `email_templates`, `newsletters`, `newsletter_approvals`, `push_subscriptions`, `social_publish_logs`

### Voting
`votes`, `vote_options`, `vote_tokens`, `vote_ballots`

### Events Media
`event_photos`

### Buddy System
`buddy_requests`, `buddy_responses`

### Club Operations
`theme_settings`, `audit_logs`, `schedule_heartbeats`, `trial_requests`, `club_partnerships`, `sync_runs`

### Infrastructure (Laravel)
`jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks`, `migrations`

---

## 4. Route Structure

### Public (no auth)
- `/` — Home page
- `/home2`, `/home3`, `/home4` — Landing page variants
- `/article/{slug}` — Public articles
- `/trial` — Trial dive request
- `/dues` — Fee calculator
- `/calendar.ics` — iCal feed
- `/contact` — Contact form
- `/health` — Health check
- `/qr/*` — Public QR codes (SEPA, payment verification)
- `/vote/{token}` — Token-based public voting

### Guest Auth
- `/login`, `/register` — Traditional auth
- `/auth/{provider}/redirect|callback` — OAuth (Google, Microsoft, Facebook, Amazon, X)
- `/forgot-password`, `/reset-password/{token}` — Password reset

### Authenticated (all verified members)
- `/profile/*` — Profile management (info, documents, certifications, avatar, emails)
- `/events/*` — Calendar, event details, registration, dive groups, photos, trip settlement
- `/members` — Members directory + trombinoscope
- `/availability` — Instructor planning calendar (read-only for members)
- `/classifieds/*` — Classified ads (CRUD)
- `/buddies/*` — Buddy finder
- `/documents/*` — Document browser
- `/gallery/*` — Photo gallery
- `/privacy/*` — GDPR consents, data export, erasure
- `/qr/*` — Personal QR codes (vCard, SEPA, federation)
- `/dive-data/*` — UDDF/DAN import/export

### Admin (bureau roles only) — prefix: `/admin`
- `/admin/dashboard` — Statistics, charts, CSV export
- `/admin/members` — Member management, impersonation
- `/admin/articles` — CMS with auto-translation
- `/admin/newsletters` — Rich HTML newsletter editor, approval workflow
- `/admin/events` — Event management (via main events routes with bureau middleware)
- `/admin/equipment` — Inventory, loans, maintenance
- `/admin/payments` — Fee generation, bank reconciliation
- `/admin/email` — Templates, compose, send log
- `/admin/votes` — Poll/election management
- `/admin/backups` — Create/inspect/download/delete
- `/admin/settings` — Federations, statuses, medical rules, maintenance rules, theme
- `/admin/seasons` — Season patterns, holiday management, event generation
- `/admin/dive-sites` — Dive site database management
- `/admin/library` — File management (upload, folders, thumbnails)
- `/admin/audit-logs` — Audit trail with export
- `/admin/partnerships` — Inter-club partnership management
- `/admin/roles` — Role/permission assignment
- `/admin/guide` — 24-page admin documentation

---

## 5. Authentication & Authorization

### Authentication
- **Session-based** using Laravel's built-in auth with a custom `divingclub` user provider
- **OAuth** via Laravel Socialite: Google, Microsoft, Facebook, Amazon, X (Twitter)
- **EU Login (CAS)** via `apereo/phpcas` for European Commission staff
- **Email verification** required before accessing protected routes
- **Login lockout** after 5 failed attempts (uses `failed_login_attempts` table)
- **Password reset** with token-based flow

### Authorization (Spatie Permission)
Six roles with hierarchical capabilities:

| Role | Scope |
|------|-------|
| `bureau_master` | Full admin access |
| `bureau_finance` | Finance + member management |
| `bureau_technical` | Equipment + dive planning |
| `instructor` | Instructor planning, event management |
| `instructor_apnea` | Apnea-specific instructor role |
| `member` | Default authenticated member |

**Middleware**:
- `CheckRole` — role-based route protection (`role:bureau_master,bureau_finance,...`)
- `EnsureEmailVerified` — blocks unverified users from protected routes
- `CheckLicense` — enforces RSA-signed license (registration gate at 100 members)
- `SetLocale` — applies user preferred locale or browser detection
- `EnsureInstalled` — redirects to install wizard if DB is empty
- `StagingBasicAuth` — HTTP basic auth on staging environments

### Impersonation
Bureau can impersonate any member via `POST /admin/members/{user}/impersonate`. Original user stored in session. Stop via `GET /admin/stop-impersonation`.

---

## 6. Email Pipeline

### Outbound — Load-Balanced Sending

The `MailBalancer` service distributes email across 3 providers:

| Provider | Daily Limit | Transport |
|----------|-------------|-----------|
| Resend (primary) | 98/day | Resend API |
| Resend (secondary) | 98/day | Resend API (second key) |
| Mailjet | 200/day (6000/month) | Sendmail/Postfix relay |

`MailBalancer::nextProvider()` picks the provider with remaining quota. `MailBalancer::configureForNext()` switches the active mailer config before each send.

**Staging safety**: `MAIL_ALWAYS_TO` redirects all outgoing mail to a test address. `MAIL_WHITELIST` allows specific addresses to pass through.

### Inbound — Alias Routing

The `MailAliasService` resolves plus-addressed email aliases to recipient groups:

| Alias Pattern | Resolves To |
|---------------|-------------|
| `cep+bureau@domain` | Bureau members |
| `cep+instructors@domain` | Active instructors |
| `cep+members@domain` | All active members |
| `cep+event.{id}@domain` | Event participants |
| `cep+members.pn1@domain` | N1 training students |
| `cep+year={YYYY}@domain` | Members by dues year |

**Processing flow** (via `PollInboundMail` job, every minute):
1. Read from Maildir or IMAP
2. Parse sender, alias, body
3. Check authorization (`MailAliasService::isAuthorized()`)
4. Clean body (`InboundMailFilter::filter()`) — strips signatures, quoted replies, disclaimers
5. Optional AI moderation (flags private/irrelevant content)
6. Forward to resolved recipients or flag for bureau review

### Signature Stripping

`InboundMailFilter` uses a multi-strategy approach:
1. **Sender-specific anchors** from `config/mail_signatures.php` (per-domain rules)
2. **HTML DOM parsing** for Outlook-style separators (blue border-top divs)
3. **Global device footers** ("Sent from my iPhone", etc.)
4. **Standard delimiters** (`-- `, `Cordialement`, `Best regards`, etc.)
5. **Corporate disclaimer patterns**

---

## 7. Scheduled Tasks

Defined in `routes/console.php`:

| Schedule | Job/Command | Purpose |
|----------|-------------|---------|
| Daily 08:00 | `SendMedicalReminders` | Medical cert expiry reminders (30/15/7/0 days) |
| Daily 09:00 | `SendEquipmentReminders` | Overdue equipment loan reminders |
| Weekly Sunday 03:00 | `WeeklyBackup` | Full backup (DB + files, retain last 4) |
| Hourly | `ProcessTranslations` | Auto-translate articles to all 15 languages |
| Every minute | `AutoOpenCloseVotes` | Open/close votes at scheduled times |
| Every minute | `PollInboundMail` | Process inbound email from Maildir/IMAP |
| Monthly 1st 04:00 | `PurgeAuditLogs` | Audit log retention cleanup |
| Monthly 1st 05:00 | `CleanupClassifieds` | Expire classified ads after 30 days |
| Every 10 min | `sync:old-events` | Sync events from legacy Joomla system |
| Hourly | `legacy:sync` | Bidirectional member data sync |
| Every 10 min | `incoming:process` | Process incoming file uploads |

All jobs report via `ScheduleHeartbeat` to the `schedule_heartbeats` table for monitoring.

---

## 8. Deployment Topology

### Production Server
- **Host**: Hetzner VPS at `204.168.168.60`
- **App path**: `/opt/deploy/apps/divingclub`
- **User**: `deploy`
- **Database**: PostgreSQL 14+
- **Web server**: Caddy (automatic HTTPS)
- **Queue**: `php artisan queue:work` (systemd service)
- **Cron**: Standard Laravel scheduler

### Local Development
- **Database**: MySQL 8+
- **Server**: `php artisan serve --port=8080`
- **Assets**: `npm run dev` (Vite HMR)

### Deployment Flow
```bash
# Local
vendor/bin/pint --dirty          # Format
php artisan test --compact       # Run tests
git push                         # Push to GitHub

# Remote (Hetzner)
cd /opt/deploy/apps/divingclub
git pull
composer install --no-dev
npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear
```

### Backup Strategy
- Weekly full backup (DB + files) via `WeeklyBackup` job
- Retention: last 4 backups kept
- Offsite upload via SFTP (if `backup.offsite_host` configured)
- Manual backups via Admin → Backups UI
- Supports MySQL (`mysqldump`), PostgreSQL (`pg_dump`), and SQLite

---

## 9. Key Services

| Service | Purpose |
|---------|---------|
| `FeeCalculationService` | Calculate membership dues (status-based + optional add-ons) |
| `BankReconciliationService` | Parse statements (text/PDF), fuzzy-match against pending payments |
| `TripSettlementService` | 5-step cost-sharing algorithm for long trips |
| `MedicalComplianceService` | Per-federation certificate evaluation, expiry calculation |
| `MailBalancer` | Load-balanced email across 3 providers |
| `MailAliasService` | Inbound alias resolution (plus-addressing) |
| `InboundMailFilter` | Strip signatures, quoted replies, AI content moderation |
| `BackupService` | Full backup (Spatie), offsite SFTP upload |
| `ArticleTranslationService` | Auto-translate articles via Google Translate API |
| `LicenseService` | RSA-signed license verification |
| `ThemeService` | Dynamic theme management (6 presets + custom) |
| `DiveGroupProposalService` | Auto-generate dive buddy groups |
| `SwapSuggestionService` | Suggest member swaps between dive groups |
| `HomogeneityAssessmentService` | Assess dive group homogeneity (14 rules) |
| `PushNotificationService` | Web push notifications |
| `SocialPublishService` | Auto-publish to social media platforms |
| `ImageQualityService` | Score uploaded photos for quality |
| `FaceDetectionService` | Detect faces in photos (privacy/GDPR) |
| `UddfService` | Import/export dive data in UDDF format |
| `DanExportService` | Export dive data in DAN DL7 format |
| `EmailStatsService` | Email sending statistics and quota tracking |
| `UpdateService` | In-app system updates |
| `NewsletterStencilSlicer` | Process newsletter template stencils |
| `ScheduleHeartbeat` | Track scheduled task execution health |

---

## 10. Frontend Architecture

- **Build**: Vite with `laravel-vite-plugin` and `sass`
- **CSS**: Bootstrap 5 + 11 custom SCSS partials (`_base`, `_dark-mode`, `_header`, `_cards`, `_tabs`, `_footer`, `_bubbles`, `_tables`, `_components`, `_ux`, `_planning`)
- **JS**: Vanilla JavaScript with `table-utils.js` providing shared utilities (search, sort, clickable rows)
- **Dark mode**: CSS class `.dark-mode` toggled via JS (not `prefers-color-scheme`)
- **Charts**: Chart.js for dashboard statistics
- **Icons**: Emoji-based (no icon library)
- **PWA**: Service worker (`sw.js`), manifest.json, offline page
- **No framework dependencies**: No React, Vue, Alpine, or jQuery
