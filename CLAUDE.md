<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- laravel/socialite (SOCIALITE) - v5
- phpunit/phpunit (PHPUNIT) - v11
- tailwindcss (TAILWINDCSS) - v3

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `socialite-development` — Manages OAuth social authentication with Laravel Socialite. Activate when adding social login providers; configuring OAuth redirect/callback flows; retrieving authenticated user details; customizing scopes or parameters; setting up community providers; testing with Socialite fakes; or when the user mentions social login, OAuth, Socialite, or third-party authentication.
- `tailwindcss-development` — Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audits, build tool configuration, and vanilla CSS.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan Commands

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`, `php artisan tinker --execute "..."`).
- Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Debugging

- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.
- To execute PHP code for debugging, run `php artisan tinker --execute "your code here"` directly.
- To read configuration values, read the config files directly or run `php artisan config:show [key]`.
- To inspect routes, run `php artisan route:list` directly.
- To check environment variables, read the `.env` file directly.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v11 rules ===

# Laravel 11+

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Laravel 11 brought a new streamlined file structure which this project now uses (carried forward in Laravel 12).

## Laravel 11+ Structure

- In Laravel 11, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- No app\Console\Kernel.php - use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Commands auto-register - files in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

## New Artisan Commands

- List Artisan commands using Boost's MCP tool, if available. New commands available in Laravel 11+:
    - `php artisan make:enum`
    - `php artisan make:class`
    - `php artisan make:interface`

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>

---

# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

DivingClub-Manager — an open-source, multi-club, multi-language diving club management system (members, events, dive planning, instructor calendar, medical compliance, payments/trip settlement, equipment, email/newsletters, voting, CMS, GDPR, backups). Laravel 12 / PHP 8.3, **Blade + Bootstrap 5** for the app UI. Originally built for the Club Européen de Plongée (CEP) in Luxembourg.

> Bootstrap 5 is the UI framework for the app itself. Tailwind is installed (`tailwind.config.js`, boost skill) but the application templates use Bootstrap utilities and SCSS partials — follow the surrounding Bootstrap conventions, not Tailwind, when editing app Blade views.

## Companion docs

- **`AGENTS.md`** — the authoritative, detailed project convention rules (Blade/`@icon` pitfalls, data-table components, forms, AJAX auto-save, trip-settlement engine internals, instructor planning, common pitfalls). **Read it before non-trivial work** in those areas; it goes well beyond what is summarized here, and it is the source of truth if the two ever disagree.
- `SPEC.md`, `REQUIREMENTS*.md`, `USER-JOURNEYS*.md`, `TESTING*.md` — product/spec/test-design references (large).

## Essential commands

```bash
composer run dev                       # serve + queue:listen + pail (logs) + vite, all concurrently
php artisan test --compact             # full test suite (PHPUnit)
php artisan test --compact --filter=testName            # single test by name
php artisan test --compact tests/Feature/SomeTest.php   # single file

vendor/bin/pint --dirty --format agent # fix code style on changed files (run before finalizing PHP changes)
vendor/bin/phpstan analyse --memory-limit=512M --no-progress   # static analysis (level 6, must be zero errors)
vendor/bin/deptrac analyse --no-progress                # verify architectural layer boundaries
vendor/bin/rector process --dry-run                     # preview strict_types / PHP 8.3 modernization

npm run build                          # build frontend assets (Vite); needed if UI changes don't appear
```

The three CI gates (`.github/workflows/ci.yml`) that must pass: **lint** (`pint --test` + phpstan), **test** (PHPUnit on PostgreSQL), **build** (`npm run build` produces `public/build/manifest.json`). Do not edit the CI workflow with `sed`.

## Architecture (big picture)

- **Request lifecycle / middleware** — configured in `bootstrap/app.php` (Laravel 11+ style, no HTTP Kernel). Chain of note: `StagingBasicAuth` (prepended), then `SetLocale` + `EnsureInstalled` (appended). Aliases: `role` → `CheckRole`, `verified.email` → `EnsureEmailVerified`. First-run setup is gated by `EnsureInstalled` + `InstallController`; `CheckLicense` enforces the RSA-signed license (free tier ≤100 members).
- **Routing** is split by concern: `routes/web.php` (member-facing), `routes/admin.php` (bureau/admin, the largest), `routes/api.php`, `routes/console.php`. ~365 routes total.
- **Layered design enforced by Deptrac** (`deptrac.yaml`): Controllers → Models/Services/Requests/Helpers/Jobs; Services → Models/Services/Helpers; Jobs → Models/Services/Helpers; Models → Models/Helpers. Never import Controllers into Services/Jobs/Models. Business logic lives in `app/Services/` (~24 services, e.g. `TripSettlementService`, `FeeCalculationService`, `MedicalComplianceService`, `EventRegistrationService`, `ArticleTranslationService`, `LicenseService`).
- **Auth & roles** — Spatie `laravel-permission` for roles (member/instructor/bureau etc.); Socialite OAuth (5 providers, incl. `socialiteproviders/microsoft`); EU Login / CAS via `apereo/phpcas`; email verification, login lockout, and impersonation.
- **Queues & scheduling** — Laravel Horizon. Background `app/Jobs/` cover translations, medical/equipment reminders, weekly backup, vote auto open/close, inbound mail polling, OCR of medical certs, audit-log purge. `app/Console/Commands/` includes legacy-site sync (`SyncOldEvents`, `LegacySyncBidirectional`) — **the sync overwrites local changes**, so guard against clobbering local cancellations (see AGENTS.md).
- **Config-driven multi-club/i18n** — no hardcoded club identity. Key config: `config/club.php`, `config/activity_types.php`, `config/cotisation.php` (fees), `config/languages.php` (15 locales), `config/mail_signatures.php`, `config/horizon.php`, `config/backup.php`.
- **Database portability** — must run on **MySQL (local dev) and PostgreSQL (CI + Hetzner staging)**. Avoid DB-specific SQL; prefer the `Schema` facade / `Model::query()`. Tests locally target MySQL (`divingclub_test`), CI targets PostgreSQL.
- **HTML sanitization** — always `App\Helpers\HtmlSanitizer::clean($html, $preset)` (`rich`/`basic`/`comment`); never instantiate HTMLPurifier directly.

## Localization

- All user-facing strings wrapped in `__()`. Code/comments in English; seed/fixture content in French. Portuguese is European Portuguese (`pt-PT`), not Brazilian.

## Deployment

- Edit locally → `vendor/bin/pint --dirty` → `php artisan test --compact` → push → merge to `main` → `ssh prod.clubcep.eu /opt/deploy/auto-deploy.sh` (or wait for the 01:00 cron) → verify.
- One Hetzner host runs **both** apps as user `clubcep`: staging `/opt/deploy/apps/divingclub` (`test.clubcep.eu`) and production `/opt/deploy/apps/divingclub-prod` (`prod.clubcep.eu`). SSH via the `test.clubcep.eu` / `prod.clubcep.eu` host aliases. Full ops detail in `.kiro/steering/deployment.md`.
- Run server artisan as `sudo -u clubcep /usr/bin/php8.3 …` (default `php` is 8.5 and lacks `mbstring`). Queues are Redis + Horizon (`supervisorctl restart horizon horizon-prod`).
- Commit message prefixes: `feat:`, `fix:`, `chore:`, `ci:`.
