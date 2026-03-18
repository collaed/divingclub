# DivingClub — Club Management System

Full-stack web application for managing the Club Européen de Plongée (CEP), a Luxembourg-based diving club. Built with Laravel 11, MySQL 8, Blade, and Bootstrap 5.

## Features

- **Member Management** — profiles, roles (6), statuses (6 with French slugs), multi-federation licences, profile photos, certification levels from 11 federations (105 levels)
- **Events & Seasons** — calendar (month/week/day), recurring patterns, registration with waiting lists, auto-promotion, WhatsApp group links, Google Maps
- **Medical Compliance** — per-federation rules, age brackets, automated expiry reminders (30/15/7/0 days), event registration gate
- **Payments** — fee calculation (base × status × age discount + optionals), bank statement reconciliation with fuzzy matching, SEPA QR codes
- **Equipment** — inventory tracking, loan management, maintenance scheduling with auto-next
- **Email** — templates with variables, group targeting (6 groups), queue with retry, full send log
- **Voting** — simple (changeable) and election (anonymous, irreversible) modes, multi-select, public live results, token-based (no login), auto-open/close, embeddable in trip proposals
- **GDPR** — consent management, JSON data export, right-to-erasure with anonymization, cookie consent
- **Dashboard** — statistics, Chart.js charts (bundled), CSV exports
- **CMS** — 11 article types with configurable background colors, image galleries (full/half/third width), threaded comments, prev/next navigation, classifieds (member self-service, 30-day auto-expiry), 7 pinned public pages (schedule, values, contact, history, bureau, members, instructors)
- **Document Library** — bureau file management (FileGator equivalent), folder organization, public/private visibility, member document browser
- **Auth** — registration, login with lockout, email verification, password reset, OAuth (5 providers), impersonation
- **i18n** — 11 locales (en, fr, de, lb, pt, it, nl, es, pl, hu, ro), browser detection + user preference
- **Theme** — 6 presets (Ocean, Coral, Lagoon, Abyss, Tropical, Arctic), custom colors, logo upload, layout options
- **PWA** — installable, offline page, service worker
- **Admin Guide** — 14-page in-app documentation with setup instructions

## Requirements

- PHP 8.3+
- MySQL 8.0+
- Node.js 18+
- Composer 2+
- Nginx or Apache

## Quick Start

```bash
# Clone and install
cd /path/to/project
composer install
npm ci && npm run build
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate
php artisan db:seed
php artisan db:seed --class=CertificationLevelSeeder
php artisan db:seed --class=SampleDataSeeder

# Storage
php artisan storage:link
mkdir -p storage/app/documents storage/app/backups

# Start
php artisan serve --port=8080
```

## Deployment

### Linux (Ubuntu 22.04+)
```bash
bash deploy.sh
```

### Windows (PowerShell)
```powershell
.\deploy.ps1 -Port 8080
```

## Test Accounts

| Email | Password | Role |
|-------|----------|------|
| admin@divingclub.eu | password | Bureau Master |
| diver@example.com | password | Member |
| marie.dupont@example.com | password | Member |

Plus 20 sample personas from `SampleDataSeeder`.

## Environment Variables

### Essential
```env
APP_URL=https://your-domain.lu
DB_DATABASE=divingclub
MAIL_MAILER=smtp
QUEUE_CONNECTION=database
CLUB_IBAN=LU00...
CLUB_ID=CEP
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

### Optional Services
```env
# Translation — free: LibreTranslate (self-hosted), Argos; paid: DeepL, Google
TRANSLATION_DRIVER=libretranslate
LIBRETRANSLATE_URL=http://localhost:5000

# OCR — free: Tesseract (local), PaddleOCR; paid: Google Vision, Azure
OCR_DRIVER=tesseract

# LLM — free: Ollama (local), LM Studio; paid: OpenAI, Anthropic
LLM_DRIVER=ollama
OLLAMA_URL=http://localhost:11434
OLLAMA_MODEL=llama3.2
```

## Architecture

```
app/
├── Http/Controllers/
│   ├── Admin/          # 10 admin controllers
│   ├── Auth/           # Login, Register, SocialAuth
│   ├── EventController, ProfileController, etc.
├── Models/             # 38 Eloquent models
├── Services/           # FeeCalculation, BankReconciliation, MedicalCompliance, Theme
├── Jobs/               # WeeklyBackup, SendMedicalReminders
├── Providers/          # ThemeServiceProvider
├── Traits/             # Auditable
database/
├── migrations/         # 16 migration files
├── seeders/            # DatabaseSeeder, SampleDataSeeder, CertificationLevelSeeder
resources/
├── views/              # 74 Blade templates
├── scss/               # Bootstrap 5 custom theme
├── js/                 # Bootstrap + Chart.js bundled via Vite
lang/                   # 11 locale directories
```

## Scheduled Tasks

| Schedule | Task |
|----------|------|
| Daily 08:00 | Medical certificate expiry reminders |
| Every minute | Vote auto-open/close |
| Sunday 03:00 | Weekly database backup (last 4 retained) |

## Certification Levels

105 levels across 11 federations: FFESSM, LIFRAS, FLASSA, NELOS, VDST, PADI, SSI, UCPA, BSAC, NASDS, CMAS. Cross-federation equivalence groups enable comparison (e.g., FFESSM N1 ≈ PADI OWD ≈ CMAS 1★ ≈ LIFRAS P1).

## Theme Presets

| Preset | Primary | Style |
|--------|---------|-------|
| Ocean | #003366 | Default navy/blue |
| Coral | #c0392b | Red/warm |
| Lagoon | #00695c | Teal/green |
| Abyss | #1a237e | Deep indigo |
| Tropical | #00838f | Cyan/orange |
| Arctic | #37474f | Grey/cool |

## License

Private — Club Européen de Plongée.
