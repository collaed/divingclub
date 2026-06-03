## architecture.md — DivingClub-Manager

## Stack

- PHP 8.3, Laravel 12, Eloquent ORM
- Blade templates (server-rendered), Bootstrap 5, SCSS (11 partials)
- Vanilla JS (event delegation, no framework), Vite bundler
- MySQL 8 (local dev), PostgreSQL 16 (CI), PostgreSQL 14 (staging)
- PHPUnit 11 (253 tests, 598 assertions)

## Numbers

| Metric | Count |
|--------|-------|
| Database tables | 131 |
| Eloquent models | 58 |
| Controllers | 62 (26 admin, 30 member, 6 auth/api) |
| Services | 28 |
| Blade views | 157 |
| Routes | 341 |
| Migrations | 78 |
| Middleware | 7 |
| Jobs | 9 |
| Commands | 8 |
| Locales | 15 |

## Directory Layout

```
app/
├── Console/Commands/       # 8 artisan commands (sync, import, scan)
├── Helpers/                # HtmlSanitizer, IconHelper
├── Http/
│   ├── Controllers/
│   │   ├── Admin/          # 26 bureau-only controllers
│   │   ├── Auth/           # Login, Register, SocialAuth, EuLogin, PasswordReset
│   │   ├── Api/            # FederationApiController
│   │   └── *.php           # 30 member-facing controllers
│   ├── Middleware/         # 7 middleware (CheckRole, SetLocale, CheckLicense...)
│   └── Requests/           # Form request validation classes
├── Jobs/                   # 9 queued/scheduled jobs
├── Models/                 # 58 Eloquent models
├── Providers/              # Service providers
├── Services/               # 28 business logic services
├── Traits/                 # Auditable trait
bootstrap/
├── app.php                 # Middleware registration, routing config
├── providers.php           # Service provider list
config/                     # Laravel + custom config (club, mail_signatures)
database/
├── factories/              # Model factories for testing
├── migrations/             # 78 migration files
├── seeders/                # Database, Sample, Certification, Equipment seeders
lang/                       # 15 locale directories (en, fr, de, lb, pt, it, nl, es, pl, hu, ro, el, et, sk, fi)
resources/
├── js/                     # app.js, table-utils.js, chart modules
├── scss/                   # 11 partials: _base, _dark-mode, _header, _cards, _tabs, _footer, _bubbles, _tables, _components, _ux, _planning
├── views/                  # 157 Blade templates
│   ├── admin/              # Bureau pages (members, events, settings, email, equipment...)
│   ├── auth/               # Login, register, verify, reset
│   ├── components/         # 17 reusable components (layout, sortable-th, filter-bar...)
│   ├── events/             # Event show, calendar, dive groups, fiche-securite-pdf
│   ├── trip-settlement/    # Member + treasurer settlement views
│   └── *.blade.php         # Profile, articles, classifieds, gallery, votes, documents
routes/
├── web.php                 # All 341 HTTP routes
├── api.php                 # Federation partner API
├── console.php             # Scheduled tasks (11 scheduled entries)
tests/
├── Feature/                # 22 test files (HTTP integration)
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
- **Services for business logic** — controllers are thin, services are testable
- **Form Request classes** — all validation in dedicated request classes
- **Multi-DB** — code must work on MySQL and PostgreSQL (Schema facade, no raw SQL)
- **Eager loading in controllers** — never lazy-load in views (prevent N+1)
