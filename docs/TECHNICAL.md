# DivingClub-Manager — Technical Documentation

## 1. Architecture Overview

DivingClub-Manager is a server-rendered monolith built on the Laravel 12 framework. It follows standard Laravel conventions with minimal custom abstractions: Eloquent ORM for data access, Blade templates for rendering, and Bootstrap 5 for the UI. There is no SPA frontend — all interactivity is achieved through Blade, vanilla JavaScript, and AJAX where needed.

### 1.1 Design Principles

- **Convention over configuration** — follows Laravel defaults wherever possible
- **Database-agnostic** — all queries work on MySQL, PostgreSQL, and SQLite
- **No external JS frameworks** — vanilla JS + Bootstrap 5, no React/Vue/Alpine
- **Multi-language first** — all user-facing strings go through `__()` / `trans()`
- **Multi-club ready** — club identity is stored in `theme_settings`, never hardcoded

### 1.2 Technology Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Language | PHP | 8.3+ |
| Framework | Laravel | 11.x |
| Database | MySQL 8+ / PostgreSQL 14+ / SQLite | |
| Frontend | Bootstrap 5, Sass, vanilla JS | 5.3 |
| Build | Vite + laravel-vite-plugin | 6.x |
| Charts | Chart.js | 4.x |
| PDF | barryvdh/laravel-dompdf | 3.x |
| QR Codes | endroid/qr-code | 5.x |
| HTML Sanitization | ezyang/htmlpurifier | 4.x |
| Push Notifications | minishlink/web-push | 10.x |
| OAuth | Laravel Socialite | 5.x |
| CAS/EU Login | apereo/phpcas | 1.6 |
| Code Style | Laravel Pint | 1.x |
| Testing | PHPUnit | 11.x |

### 1.3 Required System Components

To run DivingClub-Manager you need:

- **PHP 8.2+** with extensions: `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `gd` or `imagick`
- **Composer 2.x** for PHP dependency management
- **Node.js 18+** and **npm** for frontend asset compilation
- **A database**: MySQL 8+, PostgreSQL 14+, or SQLite 3
- **A web server**: Caddy (recommended), Nginx + PHP-FPM, or Apache
- **A mail server or SMTP relay** for email features (Mailgun, SES, etc.)
- **Cron** — one entry: `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`
- **Queue worker** (optional but recommended): `php artisan queue:work` for background jobs

For database backups:
- MySQL: `mysqldump` must be in PATH
- PostgreSQL: `pg_dump` must be in PATH

---

## 2. Directory Structure

```
app/
├── Console/Commands/          # (auto-discovered, currently empty)
├── Helpers/
│   └── HtmlSanitizer.php      # HTML purification with 3 presets (rich/basic/comment)
├── Http/
│   ├── Controllers/
│   │   ├── Admin/             # 23 controllers for bureau features
│   │   ├── Auth/              # Login, Register, SocialAuth, EuLogin (CAS)
│   │   └── (public)           # 27 controllers for member/public features
│   ├── Middleware/
│   │   ├── CheckLicense.php   # RSA license verification
│   │   ├── CheckRole.php      # Role-based access (member/instructor/bureau)
│   │   ├── SetLocale.php      # Per-user language preference
│   │   ├── EnsureInstalled.php # First-run setup gate
│   │   └── ParseEuropeanDates.php # dd/mm/yyyy → Y-m-d conversion
│   └── Requests/              # Form request validation classes
├── Jobs/
│   ├── SendMedicalReminders.php
│   └── WeeklyBackup.php
├── Models/                    # 56 Eloquent models
├── Providers/
│   └── ThemeServiceProvider.php # Injects theme CSS variables into layout
└── Services/                  # 16 service classes (business logic)

bootstrap/
├── app.php                    # Middleware registration, exception handling
└── providers.php              # Service provider registration

config/
├── club.php                   # Club-specific settings (IBAN, federation salt)
├── cotisation.php             # Fee calculation rules
├── languages.php              # Available locales and their labels
├── webpush.php                # VAPID keys for push notifications
└── (standard Laravel configs)

database/
├── migrations/                # 74 migration files
├── seeders/
│   ├── DatabaseSeeder.php     # Roles, statuses, federations, certifications
│   ├── SampleDataSeeder.php   # 20 sample members with realistic data
│   └── CepEquipmentSeeder.php # 96 equipment items from CEP inventory

resources/
├── views/                     # 126 Blade templates
│   ├── admin/                 # Admin panel views
│   │   └── guide/             # 21 in-app admin guide pages
│   ├── availability/          # Instructor availability calendar
│   ├── cms/                   # Article rendering with translations
│   ├── components/
│   │   └── layout.blade.php   # Master layout (header, nav, footer, theme)
│   ├── events/                # Calendar, event detail, registration
│   ├── home.blade.php         # Widget-based homepage
│   ├── home2.blade.php        # Modern single-page scrolling landing
│   └── profile/               # Member profile with tabbed sections
├── scss/
│   └── app.scss               # Bootstrap 5 + custom theme variables
├── js/
│   └── app.js                 # Bootstrap JS + Chart.js imports
└── lang/                      # 15 locale directories with messages.php

routes/
├── web.php                    # All 283 routes
└── console.php                # Scheduled tasks (cron definitions)

public/
├── manifest.json              # PWA manifest
├── sw.js                      # Service worker for offline support
└── images/                    # Static assets (logo, icons)
```

---

## 3. Database Schema

### 3.1 Core Tables (65 total)

**Users & Identity:**
`users`, `member_details`, `roles`, `member_statuses`, `user_emails`, `user_social_accounts`, `sessions`

**Certifications:**
`federations`, `certification_levels`, `user_certification_levels`, `member_licences`

**Events:**
`events`, `event_registrations`, `external_registrations`, `event_photos`, `seasons`, `season_patterns`, `season_holidays`

**Dive Planning:**
`dive_sites`, `dive_groups`, `dive_group_members`, `dive_group_rules`, `instructor_availabilities`

**Equipment:**
`equipment`, `equipment_loans`, `equipment_maintenance`, `equipment_maintenance_rules`

**Content:**
`articles`, `article_translations`, `article_comments`, `article_images`, `newsletters`, `newsletter_approvals`, `links`, `library_files`, `documents`

**Finance:**
`membership_fees`, `membership_fee_components`, `payment_expected`, `bank_transactions`

**Communication:**
`email_templates`, `email_log`, `push_subscriptions`, `social_publish_logs`

**Voting:**
`votes`, `vote_options`, `vote_tokens`, `vote_ballots`

**Other:**
`audit_logs`, `gdpr_consents`, `guardian_links`, `parental_consents`, `trial_requests`, `buddy_requests`, `buddy_responses`, `club_partnerships`, `medical_compliance_rules`, `theme_settings`

### 3.2 Key Relationships

- `User` → hasOne `MemberDetail` (1:1 profile data)
- `User` → belongsToMany `CertificationLevel` (pivot: `user_certification_levels`)
- `User` → hasMany `MemberLicence` (federation licences per season)
- `Event` → hasMany `EventRegistration` → belongsTo `User`
- `Event` → belongsTo `DiveSite` (optional, for outdoor events)
- `Equipment` → hasMany `EquipmentLoan` → belongsTo `User`
- `Article` → hasMany `ArticleTranslation` (one per locale)

---

## 4. Authentication & Authorization

### 4.1 Authentication Methods

1. **Email/password** — standard Laravel auth with email verification
2. **OAuth** — Google, Facebook, Microsoft, X (Twitter), Amazon via Socialite
3. **EU Login (CAS)** — European Commission's Central Authentication Service via `apereo/phpcas`

### 4.2 Role Hierarchy

| Role ID | Name | Permissions |
|---------|------|-------------|
| 1 | pending | Read-only, no event registration |
| 2 | member | Full member access |
| 3 | instructor | Member + availability calendar + event management |
| 4 | bureau_member | Instructor + admin panel (limited) |
| 5 | bureau_treasurer | Bureau + payment management |
| 6 | bureau_master | Full admin access, impersonation, settings |

Checked via `CheckRole` middleware and `User::isBureau()`, `User::isBureauMaster()`, `User::hasRole()` methods.

---

## 5. Key Services

| Service | Purpose |
|---------|---------|
| `FeeCalculationService` | Computes annual dues based on status, age, optional components |
| `BankReconciliationService` | Matches bank statement lines to expected payments using fuzzy matching |
| `MedicalComplianceService` | Checks medical certificate validity per federation rules and age brackets |
| `BackupService` | Creates tar.gz archives of DB dump + storage files with JSON manifest |
| `ThemeService` | Generates CSS custom properties from `theme_settings` table |
| `LicenseService` | Verifies RSA-signed license keys, enforces member limits |
| `ArticleTranslationService` | Auto-translates articles via Google Translate API, caches results |
| `PushNotificationService` | Sends Web Push notifications via VAPID protocol |
| `MailBalancer` | Load-balances outgoing email across Resend (×2) + Mailjet |
| `MailAliasService` | Resolves plus-addressed inbound aliases to recipient lists |
| `UpdateService` | GitHub version check + one-click update from dashboard |
| `ScheduleHeartbeat` | Tracks scheduled task execution for dashboard monitoring |
| `DiveGroupProposalService` | Auto-generates buddy pair proposals based on 14 configurable rules |
| `SocialPublishService` | Auto-publishes events/articles to social media |
| `ImageQualityService` | Scores uploaded photos for quality (resolution, blur, exposure) |
| `FaceDetectionService` | Detects faces in photos for GDPR compliance flagging |

---

## 6. Frontend Architecture

### 6.1 Asset Pipeline

- **Vite** compiles `resources/scss/app.scss` and `resources/js/app.js`
- **Bootstrap 5.3** loaded via npm, customized with Sass variables
- **Chart.js 4** for dashboard statistics charts
- **Flatpickr** for date/time pickers (loaded via CDN in layout)
- No SPA framework — all pages are server-rendered Blade templates

### 6.2 Theme System

Theme colors are stored in `theme_settings` and injected as CSS custom properties:

```css
:root {
  --dc-primary: #003366;
  --dc-secondary: #336699;
  --dc-accent: #00e5ff;
  --dc-header-start: #001a33;
  --dc-header-end: #004080;
  /* ... */
}
```

Dark mode is toggled client-side via `data-bs-theme="dark"` on `<html>`, persisted in `localStorage`.

### 6.3 PWA

- `public/manifest.json` — app manifest with icons and theme color
- `public/sw.js` — service worker caches the offline page
- Push notifications via Web Push API + `minishlink/web-push`

---

## 7. Database Compatibility

All code must work on MySQL, PostgreSQL, and SQLite. Key rules:

- Use Eloquent and query builder, avoid `DB::raw()` with DB-specific syntax
- Where raw SQL is unavoidable, use `config('database.default')` to branch:
  ```php
  $expr = config('database.default') === 'pgsql'
      ? 'EXTRACT(DOY FROM date_of_birth)'
      : 'DAYOFYEAR(date_of_birth)';
  ```
- `event_time` is stored as string on PostgreSQL — use `Str::substr($time, 0, 5)` not `->format('H:i')`
- `BackupService` uses `mysqldump` for MySQL, `pg_dump` for PostgreSQL, file copy for SQLite
- Never use `SHOW INDEX`, `SHOW TABLES`, or other MySQL-specific commands

---

## 8. Localization

### 8.1 Supported Locales (15)

`en`, `fr`, `de`, `lb`, `pt`, `it`, `nl`, `es`, `pl`, `hu`, `ro`, `el`, `et`, `sk`, `fi`

### 8.2 Translation Files

- `lang/{locale}/messages.php` — main translation file (~850 keys)
- `lang/{locale}.json` — Laravel's JSON translations for simple strings
- Portuguese (`pt`) maps to European Portuguese (pt-PT), not Brazilian

### 8.3 Article Auto-Translation

`ArticleTranslationService` uses Google Translate API to auto-translate article bodies. Translations are stored in `article_translations` and marked `stale` when the source article is updated.

---

## 9. Scheduled Tasks

Defined in `routes/console.php`:

```php
Schedule::job(new SendMedicalReminders)->dailyAt('08:00');
Schedule::job(new WeeklyBackup)->weeklyOn(0, '03:00');  // Sunday 3am
Schedule::job(new PollInboundMail)->everyMinute();
// Vote auto-open/close — every minute
// Classified expiry — monthly 1st at 04:00
// Audit log retention — monthly 1st at 05:00
// Equipment maintenance reminders — hourly
// Push queue processing — hourly
// Social auto-publish — daily at 09:00
```

All tasks report heartbeats to `schedule_heartbeats` table for dashboard monitoring.

---

## 10. Testing

- **233 tests, 532 assertions** — all PHPUnit feature tests
- Run: `php artisan test --compact`
- Tests use SQLite in-memory database with factories
- Key test areas: authentication, registration, events, payments, medical compliance, equipment loans, voting, GDPR

---

## 11. Deployment Checklist

1. `composer install --no-dev`
2. `npm ci && npm run build`
3. `cp .env.example .env` → configure database, mail, APP_URL
4. `php artisan key:generate`
5. `php artisan migrate --force`
6. `php artisan db:seed` (first deploy only)
7. `php artisan storage:link`
8. `php artisan optimize` (caches config, routes, views)
9. Set up cron for scheduler
10. Set up queue worker (optional): `php artisan queue:work --daemon`
11. Configure web server (Caddy recommended for auto-HTTPS)
12. Set CSP header to allow `https://api.open-meteo.com` and `https://archive-api.open-meteo.com` in `connect-src` for weather widgets

### Caddy Example

```
yourdomain.com {
    root * /path/to/public
    php_fastcgi unix//run/php/php8.3-fpm.sock
    file_server
    encode gzip
    header {
        Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; connect-src 'self' https://api.open-meteo.com https://archive-api.open-meteo.com; img-src 'self' data: blob: https://*.googleapis.com https://*.gstatic.com"
    }
}
```

---

## 12. Security Considerations

- **HTML sanitization**: All user HTML input goes through `HtmlSanitizer::clean()` with HTMLPurifier
- **CSRF**: All forms use `@csrf`, AJAX uses `X-CSRF-TOKEN` meta tag
- **XSS**: Blade `{{ }}` auto-escapes; `{!! !!}` only used for sanitized article bodies
- **SQL injection**: Eloquent parameterized queries throughout
- **Rate limiting**: Login attempts throttled, trial form throttled (3/min)
- **File uploads**: Validated by type and size, stored in `storage/app/public`
- **Impersonation**: Logged in audit trail, bureau_master only
- **GDPR**: Data export, right-to-erasure with anonymization, consent tracking
- **License**: RSA-signed, verified on each request via middleware
