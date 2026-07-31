## architecture.md — DivingClub-Manager

## Stack

- PHP 8.3, Laravel 12, Eloquent ORM
- Blade templates (server-rendered), Bootstrap 5, SCSS (12 partials)
- Vanilla JS (event delegation, no framework), Vite bundler
- MySQL 8 (local dev), PostgreSQL 16 (CI), PostgreSQL 14 (staging)
- PHPUnit 11 (280 tests, 670 assertions)

## Numbers

| Metric | Count |
|--------|-------|
| Database tables | 134 |
| Eloquent models | 60 |
| Controllers | 64 (27 admin, 31 member, 4 auth, 1 API, 1 health) |
| Services | 23 |
| Form Requests | 21 |
| Blade views | 164 |
| Routes | 365 |
| Migrations | 95 |
| Middleware | 7 |
| Jobs | 10 |
| Commands | 9 |
| Locales | 15 |
| Factories | 1 (UserFactory) |
| Feature tests | 26 |
| Unit tests | 15 |
| E2E tests (pytest) | 7 |

## Directory Layout

```
app/
├── Console/Commands/       # 9 artisan commands (sync, import, scan, inbound mail)
├── Helpers/                # HtmlSanitizer, IconHelper, LocaleHelper
├── Http/
│   ├── Controllers/
│   │   ├── Admin/          # 27 bureau-only controllers
│   │   ├── Auth/           # Login, Register, SocialAuth, PasswordReset
│   │   ├── Api/            # FederationApiController
│   │   └── *.php           # 31 member-facing controllers
│   ├── Middleware/         # 7 middleware (CheckRole, SetLocale, CheckLicense...)
│   └── Requests/           # 21 form request validation classes
├── Jobs/                   # 10 queued/scheduled jobs
├── Models/                 # 60 Eloquent models
├── Providers/              # Service providers (Theme, App, Route)
├── Services/               # 23 business logic services
├── Traits/                 # Auditable trait
bootstrap/
├── app.php                 # Middleware registration, routing config
├── providers.php           # Service provider list
config/                     # Laravel + custom config (club, mail_signatures)
database/
├── factories/              # Model factories (UserFactory only — expansion planned)
├── migrations/             # 95 migration files
├── seeders/                # Database, Sample, Certification, Equipment seeders
lang/                       # 15 locale directories (en, fr, de, lb, pt, it, nl, es, pl, hu, ro, el, et, sk, fi)
resources/
├── js/                     # app.js, table-utils.js, chart modules
├── scss/                   # 12 partials: _base, _dark-mode, _header, _cards, _tabs, _footer, _bubbles, _tables, _components, _ux, _layouts, _planning
├── views/                  # 164 Blade templates
│   ├── admin/              # Bureau pages (members, events, settings, email, equipment...)
│   ├── auth/               # Login, register, verify, reset
│   ├── components/         # 17 reusable components (layout, sortable-th, filter-bar...)
│   ├── events/             # Event show, calendar, dive groups, fiche-securite-pdf
│   ├── trip-settlement/    # Member + treasurer settlement views
│   └── *.blade.php         # Profile, articles, classifieds, gallery, votes, documents
routes/
├── web.php                 # Member-facing HTTP routes
├── admin.php               # Admin routes (prefixed /admin, role-gated)
├── api.php                 # Federation partner API
├── console.php             # Scheduled tasks (11 scheduled entries)
tests/
├── Feature/                # 26 test files (HTTP integration)
├── Unit/                   # 15 test files (pure logic)
├── e2e/                    # 7 pytest files (full browser journeys)
```

## Request Lifecycle

```
Browser → Vite-hashed assets (CSS/JS)
       → HTTP request
       → Global middleware: TrustProxies, StagingBasicAuth, CSRF, Session, Cookies
       → Route-specific middleware: auth, verified.email, role:bureau_*
       → Controller (fetches data via Eloquent, calls Services)
       → Blade view (renders HTML with Bootstrap 5)
       → Response (with flash messages, redirects)
```

## Middleware Stack (web group)

1. `StagingBasicAuth` — HTTP basic auth for staging (skipped if no credentials configured)
2. `EncryptCookies` → `AddQueuedCookies` → `StartSession` → `ShareErrors` → `ValidateCsrfToken` → `SubstituteBindings` (Laravel defaults)
3. `SetLocale` — detects and sets user's preferred language
4. `EnsureInstalled` — redirects to /install if DB empty (skipped in tests)

## Key Architectural Decisions

- **No SPA** — server-rendered Blade, vanilla JS for interactivity
- **No Livewire/Alpine** — event delegation + `data-*` attributes sufficient at this scale
- **Bootstrap 5 + SCSS** — not Tailwind (project started with Bootstrap, consistent throughout)
- **Services for business logic** — larger operations extracted to services; some controllers still hold complex logic (see RELEASE-PLAN-CONSISTENCY.md)
- **Form Request classes** — 21 Form Request classes exist; legacy inline validation remains in ~40 controllers (migration planned)
- **Multi-DB** — code must work on MySQL and PostgreSQL (Schema facade, no raw SQL)
- **Eager loading in controllers** — never lazy-load in views (prevent N+1)
- **3 site layouts** — Default (playful), Professional (corporate), Minimal (SaaS) — switchable from admin
- **Deptrac layer enforcement** — Controllers → Services → Models; Jobs → Services → Models; 0 violations
