## conventions.md — Coding Conventions & Project Rules

## PHP / Laravel

- PHP 8.3, Laravel 12, strict types
- Return type declarations on ALL methods
- Constructor property promotion: `public function __construct(private GitHub $github) {}`
- PHPDoc `@property` on all models, `@return` generics on relationships
- Casts in `casts()` method, not `$casts` property
- Fillable explicitly listed — never `$guarded = []`
- `Model::query()` over `DB::table()` (exception: cross-table raw operations)
- Eager load in controllers (`->with()`), never lazy-load in views
- Form Request classes for ALL validation (no inline `$request->validate()`)

## Naming

- Descriptive: `isRegisteredForDiscounts`, not `discount()`
- Test methods: `test_verb_noun_condition` (e.g. `test_guest_cannot_upload_document`)
- Commit prefix: `feat:`, `fix:`, `chore:`, `ci:`
- Enum keys: TitleCase (`FavoritePerson`, `Monthly`)
- Variables/methods: camelCase
- Routes: kebab-case (`/admin/dive-sites`)

## Controllers

- Return type on every public method
- Thin controllers — business logic in Services
- `$this->authorize()` or `abort_unless()` for auth checks
- Flash messages: `->with('success', __('...'))` on success
- Eager load relationships in the controller

## Blade Templates

- `<x-layout>` (members) or `<x-admin-layout>` (bureau) for all pages
- Page title: `@section('title', __('Page Name'))`
- Breadcrumbs on all admin pages via `<x-breadcrumb>`
- All user-facing strings wrapped in `__()`
- `@error('field') <div class="invalid-feedback">{{ $message }}</div> @enderror`
- Submit: `btn btn-primary` (create), `btn btn-outline-danger` (destructive)
- NEVER use `@icon()` inside `{{ }}` or JS strings (Blade directive, not PHP function)
- No inline `<style>` blocks (use SCSS partials)
- No inline `onclick` — use `data-*` attributes + event delegation

## Data Tables

- Sortable headers: `<x-sortable-th>` component
- Instant search: `data-instant-search="table-id"` on input
- Clickable rows: `data-href` + `clickable-rows` component (no separate View buttons)
- Filters: Excel-style dropdowns with auto-submit in `<x-filter-bar>`
- Pagination: `<x-per-page>`, default 25
- Empty state: `<p class="text-muted">{{ __('No items found.') }}</p>`

## CSS / SCSS

- Bootstrap 5 utilities first, custom SCSS only when needed
- 11 SCSS partials in `resources/scss/`
- CSS variables: `var(--dc-spacing-*)`
- Dark mode via `.dark-mode` class (never `@media prefers-color-scheme`)
- No inline styles (exception: email templates, PDF views)

## JavaScript

- Vanilla JS, no framework
- Event delegation via `data-*` attributes
- Shared utilities in `resources/js/table-utils.js`
- Toast notifications: `showToast(message, type)`
- No inline `onclick` handlers

## Testing

- PHPUnit (not Pest), every change gets a test
- Feature tests (HTTP) over Unit tests (unless pure logic)
- Use factories, not manual `::create()` in tests
- Test names: `test_verb_noun_condition`
- Must work on MySQL AND PostgreSQL
- Run: `php artisan test --compact` (all), `--filter=testName` (one)
- Create: `php artisan make:test FooTest --phpunit --no-interaction`

## Git Workflow

- Pint: `vendor/bin/pint --dirty` before every commit
- Tests: `php artisan test --compact` before every push
- Never push directly to main without local test pass
- CI: lint (Pint) + test (PHPUnit on PostgreSQL) + build (npm)

## Deployment

- Target: Hetzner VPS at 204.168.168.60, app path `/opt/deploy/apps/divingclub`
- Flow: git push → `ssh root@204.168.168.60` → `cd /opt/deploy/apps/divingclub && sudo -u clubcep git pull && php artisan migrate --force && php artisan optimize:clear`
- Never deploy without passing local tests

## Database Compatibility

- Code must work on MySQL 8 AND PostgreSQL 14+
- No DB-specific syntax (`SHOW INDEX`, `SHOW TABLES`)
- Use `Schema` facade for introspection
- Use `Schema::hasTable()` for conditional logic
- JSON columns: use `whereJsonContains()`, not raw JSON operators

## Translations & Localization

- All user-facing strings in `__()`
- French content in seeds/fixtures, English in code
- Portuguese = European Portuguese (pt-PT), not Brazilian
- 15 locales: en, fr, de, lb, pt, it, nl, es, pl, hu, ro, el, et, sk, fi

## Security

- HTML sanitization: `HtmlSanitizer::clean($html, $preset)` — presets: rich, basic, comment
- Never use `env()` outside config files — use `config('key')`
- Parameterized queries always (Eloquent handles this)
- Rate limiting on login, registration, contact forms

## AuditLog

- `AuditLog::create()` must include `'created_at' => now()` (model has `$timestamps = false`)
- Track: user_id, action, model_type, model_id, changes (JSON)
