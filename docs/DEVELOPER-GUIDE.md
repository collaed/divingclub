# DivingClub-Manager — Developer Guide

A practical onboarding guide for developers joining this project. Assumes basic PHP knowledge but not necessarily Laravel experience.

---

## Prerequisites

| Tool | Version | Install |
|------|---------|---------|
| PHP | 8.3+ | `sudo apt install php8.3 php8.3-{mbstring,xml,curl,mysql,pgsql,zip,gd,intl}` |
| Composer | 2.x | https://getcomposer.org |
| Node.js | 20+ | https://nodejs.org |
| MySQL | 8+ | `sudo apt install mysql-server` |
| Git | 2.x | `sudo apt install git` |

---

## Local Setup (15 minutes)

```bash
git clone https://github.com/collaed/divingclub.git
cd divingclub
composer install
npm ci

cp .env.example .env
php artisan key:generate
```

### Database

Create two MySQL databases:

```sql
CREATE DATABASE divingclub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE divingclub_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Update `.env`:
```env
DB_CONNECTION=mysql
DB_DATABASE=divingclub
DB_USERNAME=root
DB_PASSWORD=
```

Then:
```bash
php artisan migrate
php artisan db:seed
php artisan db:seed --class=SampleDataSeeder
php artisan storage:link
```

### Run the app

```bash
# Terminal 1: PHP server
php artisan serve --port=8080

# Terminal 2: Vite (hot-reload CSS/JS)
npm run dev
```

Visit http://localhost:8080. Login with `admin@divingclub.eu` / `password`.

---

## Project Architecture

This is a **server-rendered monolith** — no SPA, no React/Vue. Pages are rendered with Blade templates and styled with Bootstrap 5 + custom SCSS. JavaScript is vanilla (no framework), bundled with Vite.

```
app/
├── Http/Controllers/          # Route handlers (one method per action)
│   ├── Admin/                 # 26 bureau-only controllers
│   ├── Auth/                  # Login, register, OAuth, EU Login
│   └── *.php                  # Member-facing controllers
├── Models/                    # 56 Eloquent models (database entities)
├── Services/                  # 27 business logic services
├── Jobs/                      # Background tasks (queued)
├── Helpers/                   # Utility classes (HtmlSanitizer, IconHelper)
├── Http/Middleware/           # Request filters (auth, locale, CSRF)
├── Http/Requests/             # Form validation classes
database/
├── migrations/                # 74 schema changes (run in order)
├── seeders/                   # Sample data generators
├── factories/                 # Test data blueprints
resources/
├── views/                     # 155 Blade templates (HTML)
│   ├── components/            # Reusable UI pieces (x-layout, x-breadcrumb)
│   ├── admin/                 # Bureau pages
│   └── *.blade.php            # Member pages
├── scss/                      # 11 SCSS partials → compiled to CSS
├── js/                        # Vanilla JS modules
lang/                          # 15 language directories (en, fr, de, lb, pt...)
routes/
├── web.php                    # All 331 HTTP routes
├── api.php                    # API routes
├── console.php                # Scheduled tasks
tests/
├── Feature/                   # Integration tests (HTTP requests)
├── Unit/                      # Isolated logic tests
```

---

## Key Concepts for Laravel Newcomers

### Request Lifecycle

```
Browser → Route (web.php) → Middleware → Controller → View (Blade) → HTML response
```

1. **Routes** (`routes/web.php`) — maps URLs to controller methods
2. **Middleware** — runs before/after the controller (auth check, locale, CSRF)
3. **Controller** — fetches data, calls services, returns a view
4. **View** (Blade template) — HTML with `{{ $variable }}` and `@if/@foreach` directives
5. **Model** — represents a database table, handles queries via Eloquent ORM

### Eloquent ORM (Database)

Instead of writing SQL, you use PHP:

```php
// Find a user
$user = User::find(1);

// Query with conditions
$activeMembers = User::where('status_id', 2)->with('detail')->get();

// Create
Event::create(['title' => 'Pool Night', 'date' => '2026-06-01']);

// Relationships
$user->detail;              // MemberDetail (hasOne)
$user->registrations;       // EventRegistration[] (hasMany)
$event->registrations;      // Users registered for this event
```

### Blade Templates

```blade
{{-- Variable output (auto-escaped) --}}
<h1>{{ $event->title }}</h1>

{{-- Conditionals --}}
@if($user->isBureau())
    <a href="/admin">Admin Panel</a>
@endif

{{-- Loops --}}
@foreach($events as $event)
    <div>{{ $event->title }} — {{ $event->date->format('d/m/Y') }}</div>
@endforeach

{{-- Layout inheritance --}}
@extends('components.layout')
@section('title', 'My Page')
```

---

## Roles & Permissions

Uses [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission). Six roles:

| Role | Access |
|------|--------|
| `member` | Profile, events, documents, classifieds |
| `instructor` | + Instructor calendar, event management |
| `bureau_finance` | + Payments, bank reconciliation, fees |
| `bureau_technical` | + Equipment, dive sites, medical compliance |
| `bureau_master` | Full access (super admin) |
| `public` | Unauthenticated visitors |

Check in code:
```php
$user->hasRole('bureau_master');
$user->isBureau();              // Any bureau_* role
$user->hasRole('instructor');
```

---

## Member Statuses

Six statuses affecting fee calculation and access:

| Status | Description |
|--------|-------------|
| `membre_de_droit` | Full rights member |
| `actif` | Active member |
| `fonctionnaire` | Civil servant |
| `honoraire` | Honorary (no fees) |
| `junior` | Under 18 |
| `famille` | Family member (discounted) |

---

## Common Development Tasks

### Add a new page

1. Add route in `routes/web.php`
2. Create controller: `php artisan make:controller MyController`
3. Create view: `resources/views/my-page.blade.php`
4. Use `x-layout` component for the page shell

### Add a new database table

```bash
php artisan make:model MyModel -mf --no-interaction
# Creates: app/Models/MyModel.php, database/migrations/..., database/factories/...
```

Edit the migration, then: `php artisan migrate`

### Add a new admin page

1. Route in `routes/web.php` inside the admin middleware group
2. Controller in `app/Http/Controllers/Admin/`
3. View in `resources/views/admin/`
4. Add breadcrumb via `<x-breadcrumb :items="[...]" />`

### Add validation to a form

```bash
php artisan make:request StoreEventRequest --no-interaction
```

Then define rules in the `rules()` method. The controller type-hints it:
```php
public function store(StoreEventRequest $request): RedirectResponse
{
    // $request is already validated at this point
}
```

---

## Testing

Every change needs a test. We use PHPUnit (not Pest).

```bash
# Run all tests
php artisan test --compact

# Run one file
php artisan test --compact tests/Feature/EventRegistrationWorkflowTest.php

# Run one test method
php artisan test --compact --filter=test_member_can_upload_document

# Create a new test
php artisan make:test MyFeatureTest --phpunit --no-interaction
```

### Test structure

```php
class MyFeatureTest extends TestCase
{
    use RefreshDatabase;  // Resets DB between tests

    public function test_member_can_do_something(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->get('/some-page');

        // Assert
        $response->assertOk();
        $response->assertSee('Expected text');
    }
}
```

### Test database

Tests use `divingclub_test` (configured in `phpunit.xml`). The `RefreshDatabase` trait migrates and rolls back automatically.

---

## Code Style & Formatting

```bash
# Auto-fix formatting (run before every commit)
vendor/bin/pint --dirty

# Check without fixing
vendor/bin/pint --dirty --test
```

Rules: PSR-12 base, curly braces always, type hints on everything, PHPDoc for arrays/complex types.

---

## Git Workflow

```bash
# Branch naming (optional for small fixes)
git checkout -b feat/my-feature

# Commit prefixes (required)
git commit -m "feat: add dive buddy matching"
git commit -m "fix: medical reminder not sending"
git commit -m "chore: update dependencies"

# Before pushing
vendor/bin/pint --dirty
php artisan test --compact

# Push
git push -u origin feat/my-feature
```

CI runs on push: lint (Pint), tests (PHPUnit), build (npm).

---

## Deployment (Staging)

Staging server: `test.clubcep.eu` (Hetzner VPS at 204.168.168.60)

```bash
# After pushing to main:
ssh root@204.168.168.60
cd /opt/deploy/apps/divingclub
sudo -u clubcep git pull
php artisan optimize:clear
```

---

## Key Files to Know

| File | Purpose |
|------|---------|
| `routes/web.php` | All URL → controller mappings |
| `bootstrap/app.php` | Middleware registration, app config |
| `.env` | Environment variables (DB, mail, keys) |
| `phpunit.xml` | Test configuration |
| `vite.config.js` | Asset bundling (SCSS, JS) |
| `resources/scss/_base.scss` | Global styles, CSS variables |
| `resources/js/table-utils.js` | Shared table functionality (search, sort, clickable rows) |
| `config/app.php` | App name, timezone, locale |
| `database/seeders/DatabaseSeeder.php` | Default data (roles, statuses, certifications) |
| `database/seeders/SampleDataSeeder.php` | 20 fake members for development |

---

## Conventions Cheat Sheet

| Area | Convention |
|------|-----------|
| Controllers | Return type on every public method |
| Validation | Always use Form Request classes (not inline) |
| Database | `Model::query()` not `DB::table()` |
| Eager loading | Always in controller (`->with()`), never lazy in views |
| Flash messages | `->with('success', __('...'))` |
| Translations | All user-facing strings in `__()` |
| Auth checks | `$this->authorize()` or `abort_unless()` |
| CSS | Bootstrap utilities first, custom SCSS only when needed |
| Dark mode | `.dark-mode` class, never `@media (prefers-color-scheme)` |
| JS events | `data-*` attributes + event delegation, no inline `onclick` |
| Tables | `<x-sortable-th>`, `data-instant-search`, `data-href` clickable rows |

---

## Useful Artisan Commands

```bash
php artisan route:list                    # See all routes
php artisan route:list --path=admin       # Filter routes
php artisan tinker                        # Interactive PHP REPL
php artisan migrate:status                # Check migration state
php artisan db:seed --class=SampleDataSeeder  # Load test data
php artisan make:model Foo -mf            # Model + migration + factory
php artisan make:controller Admin/FooController  # New controller
php artisan make:test FooTest --phpunit   # New test
php artisan queue:work                    # Process background jobs
php artisan schedule:run                  # Run scheduled tasks
php artisan config:show mail              # View config values
```

---

## Learning Resources

- **Laravel Docs** — https://laravel.com/docs (match version 12.x)
- **Laracasts** — https://laracasts.com (video tutorials, free "Laravel from Scratch" series)
- **Blade Templates** — https://laravel.com/docs/12.x/blade
- **Eloquent ORM** — https://laravel.com/docs/12.x/eloquent
- **Testing** — https://laravel.com/docs/12.x/testing
- **Bootstrap 5** — https://getbootstrap.com/docs/5.3

---

## Getting Help

1. Read the error message — Laravel errors are descriptive
2. Check `storage/logs/laravel.log` for stack traces
3. Use `php artisan tinker` to test queries interactively
4. Run `php artisan route:list --path=foo` to find the right route
5. Look at sibling files for patterns (e.g., look at existing controllers before writing a new one)
6. Check the `docs/` folder for domain-specific guides (admin, testing, partnerships)
