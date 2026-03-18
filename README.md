# 🤿 DivingClub-Manager

Open-source diving club management system. Built with Laravel 11, MySQL 8, Blade, and Bootstrap 5. Multi-language, multi-club, mobile-ready.

Originally developed for the Club Européen de Plongée (CEP) in Luxembourg, but designed to work for any diving club worldwide.

![Club Logo](public/images/club-logo.png)

---

## Features at a Glance

| Area | Highlights |
|------|-----------|
| **Members** | Profiles, 6 roles, 6 statuses, multi-federation licences, 105 certification levels across 11 federations, profile photos, emergency contacts |
| **Events** | Calendar (month/week/day), recurring patterns, registration with waiting lists, auto-promotion, WhatsApp links, Google Maps, safety docs per dive site |
| **Dive Planning** | Trello-style group planner, 14 buddy rules, dive site database (13 sites), weather widgets, food options |
| **Instructor Calendar** | Weekly availability grid, 10 color-coded activity types (pool, kids, apnea, quarry, theory…), instructor initials, AJAX toggle |
| **Medical** | Per-federation rules, age brackets, automated expiry reminders (30/15/7/0 days), event registration gate |
| **Payments** | Fee calculator (base × status × age discount + optionals), bank statement reconciliation with fuzzy matching, SEPA QR codes |
| **Equipment** | Inventory tracking, loan management, maintenance scheduling with auto-next |
| **Email** | Templates with variables, 6 target groups, queue with retry, full send log |
| **Voting** | Simple (changeable) and election (anonymous, irreversible) modes, multi-select, live results, token-based, embeddable in trip proposals |
| **CMS** | 13 article types, image galleries, threaded comments, prev/next navigation, classifieds with 30-day auto-expiry |
| **Article Translations** | Auto-translate articles to all 11 languages, stored for instant display, tabbed reader UI matching user's preferred language |
| **Documents** | Bureau file management, folder organization, public/private visibility |
| **Free Trial** | Public trial request page with honeypot CAPTCHA, admin management |
| **GDPR** | Consent management, JSON data export, right-to-erasure with anonymization |
| **Auth** | Registration, login with lockout, email verification, password reset, OAuth (5 providers), impersonation |
| **i18n** | 11 locales: English, French, German, Luxembourgish, Portuguese, Italian, Dutch, Spanish, Polish, Hungarian, Romanian |
| **Theme** | 6 presets (Ocean, Coral, Lagoon, Abyss, Tropical, Arctic), custom colors, logo upload |
| **Multi-Club** | Dynamic club identity (name, address, IBAN, warehouse GPS), no hardcoded values |
| **License** | RSA-signed license system, free tier up to 100 members, 13-month default expiry |
| **PWA** | Installable, offline page, service worker |
| **Admin Guide** | 14-page in-app documentation |

## Numbers

- **55+ database tables**
- **195 routes**
- **11 languages**
- **105 certification levels** across 11 federations
- **13 article types**
- **10 instructor activity types**
- **6 theme presets**
- **16 passing tests** (28 assertions)

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
php artisan db:seed --class=CertificationLevelSeeder
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

### Linux (Ubuntu 22.04+)
```bash
bash deploy.sh
```

### Windows (PowerShell)
```powershell
.\deploy.ps1 -Port 8080
```

### Wasmer / Edge / Docker

The app is a standard Laravel 11 application. It should work on any PHP 8.3+ hosting that supports MySQL. For Wasmer Edge specifically:

- ✅ PHP runtime supported via WCGI
- ✅ Static assets served normally
- ✅ MySQL — use an external managed database (PlanetScale, Railway, etc.)
- ⚠️ Email — configure an external SMTP provider (Mailgun, Resend, etc.) or set `MAIL_MAILER=log` for testing
- ⚠️ File uploads — use S3-compatible storage (`FILESYSTEM_DISK=s3`) since local storage may not persist
- ⚠️ Queues — use `QUEUE_CONNECTION=sync` unless you have a worker process

```bash
# Build for Wasmer
wasmer deploy --app-name=divingclub
```

---

## Environment Variables

### Essential
```env
APP_URL=https://your-domain.lu
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
│   ├── Admin/              # 10+ admin controllers
│   ├── Auth/               # Login, Register, SocialAuth
│   ├── HomeController      # Public pages, articles
│   ├── EventController     # Events, registration
│   ├── ProfileController   # Member profiles
│   └── InstructorAvailabilityController
├── Models/                 # 40+ Eloquent models
├── Services/
│   ├── FeeCalculationService
│   ├── BankReconciliationService
│   ├── MedicalComplianceService
│   ├── ThemeService
│   ├── LicenseService      # RSA license verification
│   └── ArticleTranslationService
├── Jobs/                   # WeeklyBackup, SendMedicalReminders
├── Middleware/              # CheckLicense, SetLocale
database/
├── migrations/             # 19 migration files
├── seeders/                # Database, Sample, Certification, PinnedArticle
resources/
├── views/                  # 80+ Blade templates
├── scss/                   # Bootstrap 5 custom theme
├── js/                     # Bootstrap + Chart.js via Vite
lang/                       # 11 locale directories
scripts/
└── generate-license.php    # License key generation tool
```

## Scheduled Tasks

| Schedule | Task |
|----------|------|
| Daily 08:00 | Medical certificate expiry reminders |
| Every minute | Vote auto-open/close |
| Sunday 03:00 | Weekly database backup (last 4 retained) |

---

## User Stories

### Public Visitors
1. As a visitor, I can browse the club's public pages (schedule, values, history, contact) without logging in
2. As a visitor, I can request a free trial dive session via the trial page
3. As a visitor, I can calculate membership dues using the public calculator
4. As a visitor, I can view the site in any of 11 languages with automatic browser detection

### Members
5. As a member, I can log in and manage my profile, emergency contacts, and certifications
6. As a member, I can register for upcoming events and see my position on waiting lists
7. As a member, I can view dive sites with maps, weather, and safety documents
8. As a member, I can read articles in my preferred language via auto-translated tabs
9. As a member, I can switch languages and have my preference saved for future visits
10. As a member, I can post and manage classified ads (gear for sale, buddy requests) with 30-day auto-expiry
11. As a member, I can comment on articles in threaded discussions
12. As a member, I can participate in votes (simple or election) via secure tokens
13. As a member, I can view the instructor availability calendar to see who's teaching when
14. As a member, I can export my personal data (GDPR) or request account erasure
15. As a member, I can install the app as a PWA on my phone
16. As a member, I can generate SEPA QR codes for quick payment
17. As a member, I can browse the document library for club files and resources
18. As a member, I can use the dive group planner to organize buddy pairs

### Instructors
19. As an instructor, I can mark my availability on the weekly calendar by activity type
20. As an instructor, I can see other instructors' availability at a glance (color-coded initials)
21. As an instructor, I can manage event registrations and check medical compliance

### Bureau (Admin)
22. As an admin, I can manage all members, roles, statuses, and certifications
23. As an admin, I can create and manage events with recurring patterns
24. As an admin, I can send targeted emails to member groups using templates
25. As an admin, I can reconcile bank statements against expected payments with fuzzy matching
26. As an admin, I can manage equipment inventory, loans, and maintenance schedules
27. As an admin, I can create and manage votes (simple polls or formal elections)
28. As an admin, I can publish articles in 13 types and trigger auto-translation to all languages
29. As an admin, I can manage the document library with folder organization
30. As an admin, I can customize the club's visual theme from 6 presets or custom colors
31. As an admin, I can configure club identity (name, address, IBAN, warehouse) without touching code
32. As an admin, I can manage trial dive requests from the public
33. As an admin, I can impersonate any member to troubleshoot their experience
34. As an admin, I can view dashboard statistics with charts and export data as CSV
35. As an admin, I can manage the license key for clubs exceeding 100 members
36. As an admin, I can follow the 14-page in-app admin guide for setup and operations

### System
37. The system automatically sends medical certificate expiry reminders at 30/15/7/0 days
38. The system blocks event registration for members with expired medical certificates
39. The system auto-opens and auto-closes votes at scheduled times
40. The system performs weekly database backups retaining the last 4
41. The system verifies RSA-signed licenses and blocks registration when invalid (>100 members)
42. The system stores article translations for instant display without re-translating

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

Search Google for `"DivingClub-Manager"` to find clubs running this stack.

---

## Contributing

Pull requests welcome. The codebase follows Laravel conventions with minimal custom abstractions.

## License

MIT — Free for any diving club to use, modify, and deploy.
