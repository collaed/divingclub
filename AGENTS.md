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

=== static analysis rules ===

# Static Analysis (PHPStan, Rector, Deptrac)

## PHPStan (Level 6)

- After modifying PHP files, run `vendor/bin/phpstan analyse --memory-limit=512M --no-progress` and ensure zero errors.
- All new code must satisfy PHPStan level 6: explicit return types, parameter types, and no type mismatches.
- Do not lower the PHPStan level or add broad ignoreErrors entries without approval.

## Rector

- Configuration is in `rector.php`. Rector enforces `declare(strict_types=1)`, typed closures, dead code removal, and PHP 8.3 idioms.
- All new PHP files in `app/` must include `declare(strict_types=1)` at the top.
- Do not use `compact()` in new code — pass variables explicitly to views: `return view('x', ['a' => $a])`.
- Avoid unused variables, unreachable code, and redundant if/return patterns.

## Deptrac (Architectural Boundaries)

- Configuration is in `deptrac.yaml`. It enforces layer separation.
- **Controllers** may depend on: Models, Services, Requests, Helpers, Jobs.
- **Services** may depend on: Models, other Services, Helpers.
- **Jobs** may depend on: Models, Services, Helpers.
- **Models** may depend on: other Models, Helpers.
- **Middleware** may depend on: Models, Services, Helpers.
- **Helpers** may depend on: Models.
- Never import Controllers from Services, Jobs, or Models. Never import Middleware from Jobs.
- Run `vendor/bin/deptrac analyse --no-progress` to verify if you are unsure about a dependency direction.

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

=== project rules ===

# DivingClub-Manager Project Rules

## Blade Templates

- **NEVER use `@icon()` inside `{{ }}`, `__()`, JavaScript strings, `innerHTML`, `onclick` handlers, or HTML attribute values.** `@icon()` is a Blade directive compiled at template level — it cannot be nested inside PHP expressions or JS. Use raw emoji characters instead (e.g. `🤿` not `@icon('🤿')`).
- `@icon()` is ONLY safe at the start of HTML content or after `>` in tags (e.g. `<span>@icon('📧') text</span>`).

## HTML Sanitization

- Use `App\Helpers\HtmlSanitizer::clean($html, $preset)` for all HTML purification. Three presets: `rich` (articles, events), `basic` (classifieds), `comment` (comments). Never instantiate HTMLPurifier directly in controllers.

## Database Compatibility

- Code must work on both MySQL (local dev) and PostgreSQL (Hetzner staging). Avoid DB-specific syntax (e.g. `SHOW INDEX`). Use `Schema` facade or try/catch for cross-DB operations.

## Deployment

- SSH: `root@204.168.168.60`, app path: `/opt/deploy/apps/divingclub`, deploy user: `deploy`
- Flow: edit locally → `vendor/bin/pint --dirty` → `php artisan test --compact` → git push → pull on Hetzner → `php artisan optimize:clear`
- Commit messages: `fix:`, `feat:`, `chore:` prefix

## Localization

- French content in seeds/fixtures, English in code
- Portuguese must be European Portuguese (pt-PT), not Brazilian
- Google Translate API: `pt` locale maps to `pt-PT`

## Privacy

- Regular members cannot see other members' email or phone — only the profile owner and bureau roles

</laravel-boost-guidelines>

## Page Structure

- Every authenticated page uses `x-layout` (members) or `x-admin-layout` (bureau). Exceptions: home2, home3 (public landing), welcome, fiche-securite-pdf (print).
- Page title set via `@section('title', __('Page Name'))`.
- Breadcrumbs on all admin pages via `x-breadcrumb`.
- No inline `<style>` blocks — use SCSS partials. Exception: email templates and PDF views.
- No inline `onclick` handlers — use `data-*` attributes + event delegation in JS. Exception: dark mode toggle, QR scanner, clipboard copy (simple one-liners).

## Data Tables (all pages)

- **Sortable headers**: All data tables must use `<x-sortable-th>` for sortable columns with ↑↓ indicators.
- **Instant search**: Use `data-instant-search="table-id"` on search inputs for immediate JS-powered filtering without page reload.
- **Clickable rows**: All table rows that represent a viewable entity must use `data-href` + the `clickable-rows` component. Do not add separate "View" buttons — the whole row is the link. Buttons/forms inside rows (e.g., Return, Delete) must still work without triggering row navigation.
- **Filters**: Use Excel-style dropdowns with auto-submit, wrapped in `x-filter-bar`.
- **Pagination**: Use `x-per-page` component, default 25 rows.
- **Empty state**: `<p class="text-muted">{{ __('No items found.') }}</p>`

## Forms & Validation

- All new forms must use Form Request classes (never inline `$request->validate()` in controllers). Existing inline validation is legacy debt — bring into compliance when touching the file.
- Labels use `__()` for translation.
- Error display: `@error('field') <div class="invalid-feedback">{{ $message }}</div> @enderror`
- Submit buttons: `btn btn-primary` (create/save), `btn btn-outline-danger` (delete/destructive).
- Confirmation on destructive actions: `data-confirm="message"` attribute.

## Controllers

- Return type declaration on every public method.
- Eager load relationships (`->with()`) in the controller — never lazy load in views.
- Flash messages: `->with('success', __('...'))` on success; validation handles errors automatically.
- Authorization: use `$this->authorize()` or `abort_unless()` for bureau actions.
- Prefer `Model::query()` over `DB::table()`. Use `DB::` only for cross-table operations or raw performance needs.

## Models

- `@property` PHPDoc on all models (PHPStan level 6 enforced).
- `@return` generics on relationships (`HasMany<Event>`).
- Casts in `casts()` method, not `$casts` property.
- Fillable explicitly listed — no `$guarded = []`.
- AuditLog::create must always include `'created_at' => now()` (model has `$timestamps = false`).

## JavaScript & Frontend

- No inline `onclick` — use `data-*` attributes + event delegation.
- Shared utilities in `table-utils.js` (search, sort, clickable rows).
- Toast notifications via `showToast(message, type)`.
- Activity type colors defined in `_planning.scss` only — never inline styles.
- **AJAX auto-save for management tables**: When a bureau page has per-row editable fields (dropdowns, number inputs), use silent AJAX save on `change`/`blur` with a 300ms debounce — no per-row Save buttons. Show a brief "✓ Saved" status indicator. Controller methods must return JSON for AJAX requests (`$request->ajax()`) and redirect for non-AJAX (graceful degradation). Reference implementation: `trip-settlement/manage.blade.php`.

### Pages that should use AJAX auto-save (when next touched):
- `admin/settings/index` — per-section forms (27 forms, convert section-by-section)
- `admin/dive-group-rules/index` — per-rule inline fields
- `trip-settlement/manage` — ✅ already done

## CSS/SCSS

- Use CSS variables (`var(--dc-spacing-*)`) for spacing.
- Dark mode via `.dark-mode` class, never `@media (prefers-color-scheme)`.
- Bootstrap utilities first, custom CSS only when Bootstrap can't do it.
- 11 SCSS partials: _base, _dark-mode, _header, _cards, _tabs, _footer, _bubbles, _tables, _components, _ux, _planning.

## Translations & Localization

- All user-facing strings wrapped in `__()` — including placeholders, titles, button labels.
- French content in seeds/fixtures, English in code.
- Portuguese = European Portuguese (pt-PT), not Brazilian.
- Google Translate API: `pt` locale maps to `pt-PT`.

## Testing

- Every feature gets a Feature test (not Unit, unless pure logic).
- Use model factories — not manual `::create()` in tests.
- Test names: `test_verb_noun_condition` (e.g., `test_guest_can_register`).
- Tests must work on both MySQL (local) and PostgreSQL (CI). Use `Schema::hasTable('legacy_roles')` for role table detection.

## Git & CI

- Commit prefix: `feat:`, `fix:`, `chore:`, `ci:`
- Pint + PHPStan clean before every push.
- All 3 CI jobs must pass (lint, test, build) before deploying.
- No direct push to main without local test pass.
- CI workflow: `.github/workflows/ci.yml` — do not edit with sed (rewrite cleanly if changes needed).
- **After pushing, check CI status** with `gh run list --limit 1`. If it fails, fix before moving on. Use `gh run view <id> --log-failed` to diagnose.
- CI runs on PostgreSQL — code must work on both MySQL (local dev) and PostgreSQL (CI). Use `Schema::hasTable('legacy_roles')` for role table detection.

## Instructor Planning & Activity Types

- **Activity types describe WHAT is done**, never where or when. Location and schedule are separate event fields. Do not create activity types that duplicate location (e.g., "Merl vendr.") or schedule info.
- Current activity types: `pool` (generic pool), `pool_kids` (children), `pool_pn1` (PN1 training), `pool_pn23` (PN2-PN3 training), `apnea`, `fosse`, `quarry` (quarry/lake), `long_trip`, `theory`, `social`.
- **Instructor initials and colors** are stored in `member_details.instructor_initial` and `member_details.instructor_color`. These match the old Google Sheet planning. Do not auto-generate — they are manually assigned and must remain stable.
- **Jerome disambiguation**: Jerome Samson = J (first Jerome), Jerome Tongio = T, Jérôme Boisseau = B. Pietro = O (not P, which is Pascale). Manuel = U. Valérie = A. Luc = C.
- **Legend categories**: Instructors (Spatie role `instructor` or `instructor_apnea`) and Bureau non-instructors (bureau roles without instructor role) are shown separately.
- **Wednesday pool blocks**: Two timeslots per Wednesday (17:00-18:30 and 18:30-20:00), created as separate events. The `event_type` determines which group gets tank priority (PN1, kids, or generic pool). Both blocks share the same type each week.
- **Side-by-side display**: When multiple events fall on the same day, the instructor planning shows them side by side (not stacked), sorted by `event_time`. Each slot is colored by its `event_type`.
- **`active_instructor` on `member_details`** must only be true for actual instructors (people who lead training). Bureau members who participate but don't instruct should have it false.

## Equipment Management

- **Clickable rows**: Equipment table rows link to the detail page. Use `data-href` + `clickable-rows` component. Do not add separate "View" buttons — the whole row is the link.
- **Filters**: Equipment index must have filters for type, status, location, and a free-text size filter (searches in name). All filters use Excel-style dropdowns with auto-submit.

## Incremental Compliance

- New code MUST follow all rules above.
- Existing code: bring into compliance when touching a file for a feature/fix. Do not refactor files you're not otherwise changing.
- Priority order for cleanup: onclick handlers > missing translations > inline validation > sortable headers.

## Trip Settlement Engine

- **Scope**: Only for `long_trip` events with `trip_settlement_enabled = true`. Not all events have cost-sharing.
- **Transit mode** (`van`, `fly`, `own`) is stored on `event_registrations.transit_mode` — chosen at registration time.
- **Trip participants** (`trip_participants` table) track `driving_percentage` and `local_transit_days` per member per event. Bureau manages these values.
- **Receipts** (`trip_receipts` table) have two categories: `general` (shared equally) and `transit` (van riders only). Status flow: `pending` → `approved`/`rejected`.
- **5-step algorithm** in `TripSettlementService::calculate()`:
  1. Global pool: sum approved `general` receipts, divide equally among all participants.
  2. Local transit subsidy: fly-in members pay `local_daily_charge × local_transit_days`.
  3. Long-haul transit pool: sum approved `transit` receipts.
  4. Driver bounties: `driver_bounty_total` distributed by `driving_percentage` (e.g. 50%/50% for two drivers, or 30%/30%/40% for three).
  5. Final balance: `(owes) - (bounty_credit + total_paid)`. Positive = member owes club. Negative = club owes member.
- **Money conservation**: The sum of all participant balances must equal zero. Tests verify this.
- **Settlement status**: `open` (receipts can be submitted/approved) or `closed` (ledger locked, no changes).
- **Receipt images** stored via `Storage::disk('local')` in `trip-receipts/{event_id}/`.

## Newsletter & Generated Content

- **Newsletter theme assets** (header.jpg, footer.jpg, SVGs) belong in `public/images/newsletter/<theme>/` and ARE tracked in git.
- **Generated newsletter HTML** (per-issue output) goes to `storage/app/public/newsletters/published/` — accessible via `/storage/newsletters/published/`.
- **Generated artwork** (AI images, variants) goes to `storage/app/public/newsletters/` — never in `public/images/`.
- **Rule**: Never create files in `public/` at runtime. Use `Storage::disk('public')` for all generated content.


## Common Pitfalls (Hard-Won Lessons)

### Adding Database Columns
- **ALWAYS** add new columns to the model's `$fillable` array. `$model->update()` silently ignores unfillable fields.
- After adding a column, check BOTH the migration AND the model before committing.

### Blade Templates
- **NEVER use `@icon()` inside `{{ }}`, `__()`, JavaScript strings, `innerHTML`, `onclick` handlers, or HTML attribute values.** `@icon()` is a Blade directive compiled at template level — it cannot be nested inside PHP expressions or JS. Use raw emoji characters instead (e.g. `🤿` not `@icon('🤿')`).
- `@icon()` is ONLY safe at the start of HTML content or after `>` in tags (e.g. `<span>@icon('📧') text</span>`).
- **NEVER** put closures with array brackets inside `@json()` — Blade's parser confuses `['confirmed','waiting']` brackets with directive closing. Move filtering to a `@php` block above.
- When using `$isPrivileged` or similar computed vars, ensure they're defined BEFORE first use (not just in the sidebar `@php` block).

### Non-Member (null user_id) Handling
- `Collection::firstWhere('user_id', null)` returns the FIRST null match. When multiple non-members exist, **match by name** instead: `->first(fn($p) => $p['user_id'] === null && $p['name'] === $tp->non_member_name)`
- Any code that accesses `$something[$user_id]` as an array key will trigger deprecation when `$user_id` is null. Guard with `$user_id ? $array[$user_id] : default`.
- Always test with at least 2 non-members to catch null-matching bugs.

### Sync (SyncOldEvents)
- The sync runs on a cron and will **overwrite** local changes. If a registration is cancelled locally but confirmed on the old site, the sync reverts it.
- Rule: before `updateOrCreate`, check if the existing record is locally cancelled — skip if so.

### Validation Rules
- When adding a new `category` option (e.g. 'diving', 'individual'), search for ALL `in:general,transit` validation rules in the controller — there are typically 3-4 occurrences.
- Use `grep -n "in:general" app/Http/Controllers/` to find them all.

### Trip Settlement
- Receipt categories: `general` (all share), `transit` (van riders share), `diving` (club invoice), `individual` (charged to one person).
- `individual` receipts with `is_third_party=true` are charges TO a person (not expenses BY them). They increase what the person owes.
- `diving` receipts are club-level invoices. They appear in accounting but don't directly affect individual balances (individual dive charges do that).
- `instructor_daily_subsidy` × trip_days is credited to participants flagged `is_supervising_instructor`.
- Non-member participants use `prepaid_amount` directly on `trip_participants` (no PaymentExpected for them).
- **Instructor subsidy** uses `supervising_days` (number, not boolean) on `trip_participants`. Being `active_instructor` is a prerequisite shown as badge, but you must set days > 0 to receive subsidy.
- **`dive_days`** on event = number of actual diving days (may differ from trip length). Used for instructor subsidy calculation.
- **Event `$fillable`**: when adding ANY new column to events, always add it to `$fillable` in `app/Models/Event.php`. Same for `TripParticipant`.
- **Auto-refresh**: the manage page reloads 1.5s after any successful auto-save so totals/cards update. Don't remove this — it prevents stale data display.
- **Instructor subsidy is a SHARED cost** — it's added to the global pool (everyone pays), then credited back to the instructor. Not a transit cost.
- **Dive pricing fields** (`dive_unit_price`, `nitrox_supplement`, `instructor_daily_subsidy`, `dive_days`) are on the `events` table and MUST be in Event `$fillable`.
- **Auto-save + checkboxes**: unchecked checkboxes don't send values. Always add `<input type="hidden" name="field" value="0">` before the checkbox, or use a number input instead.
- **Select fields with nullable option**: when a `<select>` has `<option value="">Use default</option>`, empty string means null — do NOT cast `(int) ""` as that gives `0`. Use `$request->input('field') === '' ? null : (int) $request->input('field')`.
- **Intervention Image v4 API**: use `Image::decode($content)` not `Image::read()`. Use `->encodeUsingMediaType('image/jpeg', quality: 85)` not `->toJpeg()`. Check ALL controllers using Image when upgrading.
- **Deleted FK references**: when deleting equipment/records, orphaned loans/references cause null errors. Always guard with `@if($relation)` in blade or eager-load with `->whereHas()`.
- **XLSX export** must mirror the manage page structure. When adding columns/features to the UI, update the export method in `TripSettlementController::export()` too.
- **4 summary cards**: Shared Costs (global + instructor subsidy), Transit (fuel + bounties), Diving (invoice vs charges), Local Subsidy. Instructor subsidy belongs in Shared, NOT Transit.
- **Abbreviations**: Nitrox = EAN (Enriched Air Nitrox), never N₂ (that's nitrogen).
- **Instructor subsidy is a SHARED cost** — it's added to the global pool (everyone pays), then credited back to the instructor. Not a transit cost.
