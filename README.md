# 🤿 DivingClub-Manager

Open-source diving club management system. Built with Laravel 12, PHP 8.3, Blade, and Bootstrap 5. Multi-language, multi-club, mobile-ready.

Originally developed for the Club Européen de Plongée (CEP) in Luxembourg, but designed to work for any diving club worldwide.

![Club Logo](public/images/club-logo.png)

---

## Features at a Glance

| Area | Highlights |
|------|-----------|
| **Members** | Profiles, 6 roles, 6 statuses, multi-federation licences, 105 certification levels across 11 federations, profile photos, emergency contacts |
| **Events** | Calendar (month/week/day), recurring patterns, multi-day events, registration with waiting lists, auto-promotion, WhatsApp links, Google Maps, safety docs per dive site |
| **Dive Planning** | Trello-style group planner, 14 buddy rules, dive site database (13 sites), weather widgets (Open-Meteo), food options |
| **Instructor Calendar** | Weekly availability grid, 10 color-coded activity types (pool, kids, apnea, quarry, theory…), instructor initials, AJAX toggle, auto-registration |
| **Medical** | Per-federation rules, age brackets, automated expiry reminders (30/15/7/0 days), event registration gate |
| **Payments** | Fee calculator (base × status × age discount + optionals), assisted bank statement reconciliation with fuzzy matching, SEPA QR codes |
| **Equipment** | Inventory tracking with short numbers, loan management, quick-loan by type, BCD size matching, maintenance scheduling with auto-next, location tracking |
| **Email** | Templates with variables, 6 target groups, queue with retry, full send log, load-balanced across 3 providers |
| **Newsletters** | Rich HTML newsletters, themed templates, AI-generated artwork, approval workflow, test send |
| **Voting** | Simple (changeable) and election (anonymous, irreversible) modes, multi-select, live results, token-based, embeddable in trip proposals |
| **CMS** | 13 article types, image galleries, threaded comments, prev/next navigation, classifieds with 30-day auto-expiry |
| **Article Translations** | Auto-translate articles to all 15 languages, stored for instant display, tabbed reader UI matching user's preferred language |
| **Backup** | Admin UI: create/inspect/download/delete, DB + files archive with manifest, MySQL, PostgreSQL & SQLite support, weekly auto-backup with retention |
| **Documents** | Bureau file management, folder organization, public/private visibility |
| **Free Trial** | Public trial request page with honeypot CAPTCHA, admin management |
| **GDPR** | Consent management, JSON data export, right-to-erasure with anonymization |
| **Auth** | Registration, login with lockout, email verification, password reset, OAuth (5 providers), EU Login (CAS), impersonation |
| **i18n** | 15 locales: English, French, German, Luxembourgish, Portuguese, Italian, Dutch, Spanish, Polish, Hungarian, Romanian, Greek, Estonian, Slovak, Finnish |
| **Theme** | 6 presets (Ocean, Coral, Lagoon, Abyss, Tropical, Arctic), custom colors, logo upload, dark mode |
| **Homepage** | Configurable widget layout with drag-and-drop: hero slideshow, articles, upcoming events, photo gallery, quick links, custom HTML. Per-widget visibility (public/members/instructors/bureau) |
| **Multi-Club** | Dynamic club identity (name, address, IBAN, warehouse GPS, training locations), no hardcoded values |
| **License** | RSA-signed license system, free tier up to 100 members, 13-month default expiry |
| **PWA** | Installable, offline page, service worker, push notifications |
| **Admin Guide** | 24-page in-app documentation |

## Numbers

- **131 database tables**
- **341 routes**
- **15 languages**
- **105 certification levels** across 11 federations
- **13 article types**
- **10 instructor activity types**
- **6 theme presets**
- **58 Eloquent models**
- **157 Blade templates**
- **28 services**
- **267 passing tests** (634 assertions)

---

## Quick Start

```bash
git clone https://github.com/collaed/divingclub.git
cd divingclub
composer install
npm ci && npm run build
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate
php artisan db:seed
php artisan db:seed --class=SampleDataSeeder

# Storage & serve
php artisan storage:link
php artisan serve --port=8080
```

### Test Accounts

| Email | Password | Role |
|-------|----------|------|
| admin@divingclub.eu | password | Bureau Master |
| diver@example.com | password | Member |

Plus 20 sample personas from `SampleDataSeeder`.

---

## Deployment

### Linux / macOS
```bash
composer install --no-dev
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

### Supported Databases

- **MySQL 8+** — recommended for production
- **PostgreSQL 14+** — fully supported (used on staging)
- **SQLite** — supported for development/testing

### Web Servers

- **Caddy** (recommended) — automatic HTTPS, simple config
- **Nginx** + PHP-FPM
- **Apache** + mod_php

---

## Environment Variables

### Essential
```env
APP_URL=https://your-domain.lu
DB_CONNECTION=mysql
DB_DATABASE=divingclub
MAIL_MAILER=smtp
QUEUE_CONNECTION=database
```

### Club Identity (also configurable via Admin → Settings)
```env
CLUB_ID=MYCLUB
CLUB_IBAN=LU00...
FEDERATION_SALT=random_string
```

### OAuth (all free to set up)
```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
FACEBOOK_CLIENT_ID=
FACEBOOK_CLIENT_SECRET=
MICROSOFT_CLIENT_ID=
MICROSOFT_CLIENT_SECRET=
GOOGLE_MAPS_KEY=
```

---

## Architecture

```
app/
├── Http/Controllers/
│   ├── Admin/              # 26 admin controllers
│   ├── Auth/               # Login, Register, SocialAuth, EuLogin
│   ├── HomeController      # Public pages, articles, home2 landing
│   ├── EventController     # Events, registration, calendar
│   ├── ProfileController   # Member profiles
│   └── InstructorAvailabilityController
├── Models/                 # 56 Eloquent models
├── Services/
│   ├── FeeCalculationService
│   ├── BankReconciliationService
│   ├── MedicalComplianceService
│   ├── BackupService        # DB + files backup, manifest, inspection
│   ├── ThemeService
│   ├── LicenseService       # RSA license verification
│   ├── ArticleTranslationService
│   ├── PushNotificationService
│   └── 12 more...
├── Jobs/                   # WeeklyBackup, SendMedicalReminders
├── Middleware/             # CheckLicense, SetLocale, CheckRole, etc.
database/
├── migrations/             # 74 migration files
├── seeders/                # Database, Sample, Certification, Equipment
resources/
├── views/                  # 155 Blade templates
├── scss/                   # Bootstrap 5 custom theme
├── js/                     # Bootstrap + Chart.js via Vite
lang/                       # 15 locale directories
```

## Scheduled Tasks

| Schedule | Task |
|----------|------|
| Daily 08:00 | Medical certificate expiry reminders |
| Every minute | Vote auto-open/close |
| Sunday 03:00 | Weekly full backup — DB + files (last 4 retained) |
| Monthly 1st 04:00 | Classified ads auto-expiry cleanup |
| Monthly 1st 05:00 | Audit log retention cleanup |
| Hourly | Push notification queue processing |
| Daily 09:00 | Social media auto-publish |

---

## User Stories

### Public Visitors
1. Browse public pages (schedule, values, history, contact) without logging in
2. Request a free trial dive session via the trial page
3. Calculate membership dues using the public calculator
4. View the site in any of 15 languages with automatic browser detection
5. View the modern landing page (/home2) with full club presentation

### Members
6. Log in and manage profile, emergency contacts, and certifications
7. Register for upcoming events and see position on waiting lists
8. View dive sites with maps, weather forecasts, and safety documents
9. Read articles in preferred language via auto-translated tabs
10. Switch languages with preference saved for future visits
11. Post and manage classified ads (gear for sale, buddy requests) with 30-day auto-expiry
12. Comment on articles in threaded discussions
13. Participate in votes (simple or election) via secure tokens
14. View the instructor availability calendar to see who's teaching when
15. Export personal data (GDPR) or request account erasure
16. Install the app as a PWA on phone
17. Generate SEPA QR codes for quick payment
18. Browse the document library for club files and resources
19. Use the dive group planner to organize buddy pairs
20. Borrow equipment via quick-loan (BCDs sorted by size preference)
21. Find dive buddies matching experience level and preferences
22. Receive push notifications for event updates

### Instructors
23. Mark availability on the weekly calendar by activity type (auto-registers for events)
24. See other instructors' availability at a glance (color-coded initials)
25. Manage event registrations and check medical compliance

### Bureau (Admin)
26. Manage all members, roles, statuses, and certifications
27. Create and manage events with recurring patterns and multi-day support
28. Send targeted emails to member groups using templates
29. Reconcile bank statements against expected payments with fuzzy matching
30. Manage equipment inventory, loans, and maintenance schedules
31. Create and manage votes (simple polls or formal elections)
32. Publish articles in 13 types and trigger auto-translation to all languages
33. Manage the document library with folder organization
34. Customize the club's visual theme from 6 presets or custom colors
35. Configure club identity (name, address, IBAN, training locations) without touching code
36. Manage trial dive requests from the public
37. Impersonate any member to troubleshoot their experience
38. View dashboard statistics with charts and export data as CSV
39. Manage the license key for clubs exceeding 100 members
40. Follow the 24-page in-app admin guide for setup and operations
41. Create, inspect, download, and delete backups (DB + files) from the admin UI
42. View the bureau worklist showing pending actions (unverified certs, expiring medical, missing IBAN, minors without guardian, upcoming birthdays, next events)
43. Configure homepage widget layout with drag-and-drop
44. Manage dive sites with depth, conditions, safety info, and weather integration
45. Create and send rich HTML newsletters with approval workflow
46. Manage inter-club partnerships

### System
47. Automatically sends medical certificate expiry reminders at 30/15/7/0 days
48. Blocks event registration for members with expired medical certificates
49. Auto-opens and auto-closes votes at scheduled times
50. Performs weekly full backups (DB + files) retaining the last 4
51. Verifies RSA-signed licenses and blocks registration when invalid (>100 members)
52. Stores article translations for instant display without re-translating
53. Supports MySQL, PostgreSQL, and SQLite databases

---

## Certification Levels

105 levels across 11 federations: FFESSM, LIFRAS, FLASSA, NELOS, VDST, PADI, SSI, UCPA, BSAC, NASDS, CMAS.

Cross-federation equivalence groups enable comparison (e.g., FFESSM N1 ≈ PADI OWD ≈ CMAS 1★ ≈ LIFRAS P1).

## Theme Presets

| Preset | Primary | Style |
|--------|---------|-------|
| Ocean | #003366 | Default navy/blue |
| Coral | #c0392b | Red/warm |
| Lagoon | #00695c | Teal/green |
| Abyss | #1a237e | Deep indigo |
| Tropical | #00838f | Cyan/orange |
| Arctic | #37474f | Grey/cool |

---

## Discoverability

Every DivingClub-Manager instance includes:
- `<meta name="generator" content="DivingClub-Manager/1.0">` in the HTML head
- An HTML comment: `<!-- Powered by DivingClub-Manager -->`
- A footer link back to the GitHub repository

---

## Testing

267 PHPUnit tests (634 assertions) covering unit logic, HTTP workflows, and data integrity.

```bash
php artisan test --compact                    # Run all tests
php artisan test --compact --filter=testName  # Run one test
```

| Layer | Files | Focus |
|-------|-------|-------|
| Unit (15) | Services, models, helpers | Pure logic, no DB |
| Feature (21) | HTTP routes, auth, workflows | Full request/response cycle |
| E2E (7) | Multi-step journeys | Against live server |

See [`tests/TESTING-REQUIREMENTS.md`](tests/TESTING-REQUIREMENTS.md) for the full testing strategy, and [`docs/DEVELOPER-GUIDE.md`](docs/DEVELOPER-GUIDE.md) for developer onboarding.

---

## Contributing

Pull requests welcome. The codebase follows Laravel conventions with minimal custom abstractions.

## License

MIT — Free for any diving club to use, modify, and deploy.
