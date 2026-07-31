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
- 12 SCSS partials in `resources/scss/`
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

## Trip Settlement & Accounting

- **Double-entry visibility**: Every financial operation must show both sides of the transaction. When the club advances money on behalf of a member, two records must exist:
  - An `individual` charge to the member (increases what they owe)
  - A `memo` category receipt (audit trail showing the club paid out)
- **Category `memo`**: Never counted in any settlement pool. Excluded from `TripSettlementService` calculations. Purely for audit/bookkeeping visibility.
- **Auto-sync**: When non-diving individual charges are created/edited/deleted, a single `[AUTO]` memo receipt is auto-maintained summarizing all club advances.
- **Dive charges vs other individual charges**: The diving section header only compares dive-related individual charges (matching `dive|plong|nitrox|EAN` in description) against the dive invoice. Non-dive individual charges (bar tabs, transport, etc.) are shown separately.
- **`[AUTO]` prefix**: Marks system-managed receipts. Never manually edit these — they're regenerated on every change.
- **Categories**: `general` (shared equally), `transit` (van riders), `diving` (club invoice from dive center), `individual` (charged to one person), `memo` (audit-only, no financial impact)
- **`is_third_party` boolean**: Marks invoices paid by the club to external vendors (not member out-of-pocket expenses)

## Newsletter System

- **Dual storage**: Newsletters have both structured `slots` (article references for the admin editor) AND `published_html` (the exact email that was sent, for visual archive)
- **Static hosting**: Newsletters are also hosted as static HTML on `clubcep.ecb.pm` with bilingual FR/EN article pages. FR is default, EN via link in bottom-right corner of each card.
- **Article naming**: Static files use numeric IDs (`{month_prefix}{position}.html`, e.g. `401.html` for June slot 1). Index pages: `{month}-index.html`.
- **Graphic elements** for the email template (per theme folder):
  - `header.jpg` (600×150px) — top banner
  - `footer.jpg` (600×120px) — bottom banner
  - `row1-left.jpg` / `row2-left.jpg` (45×300px) — left decorative wall
  - `row1-center.jpg` / `row2-center.jpg` (45×300px) — center column separator
  - `row1-right.jpg` / `row2-right.jpg` (44×300px) — right decorative wall
  - `h-separator.jpg` (600×35px) — horizontal divider between rows
- **Translation debounce**: Article translations are dispatched with a 2-minute delay after save. Rapid edits don't trigger redundant translations — only the final version gets translated.
- **Article roles** (planned): Stable identifier for menu-linked articles so renaming/translating titles doesn't break navigation.

## Voting System

- **Vote Groups**: Bundle multiple questions into one ballot (e.g. "Approve accounts" + "Elect board"). One token per member grants access to all questions.
- **Token generation**: Excludes former members (`status.slug = 'former'`). Uses `vote_group_id` on `vote_tokens`.
- **Election mode**: Anonymous (`token_hash`), irreversible, `num_positions` limits selections. Results hidden until vote is closed.
- **Simple mode**: Allows vote change, shows live results if `is_public`.
- **Consumed tokens**: Voter sees their previous choices grayed out with submission timestamp. No re-submission unless `allow_change`.
- **Staging emails**: When `STAGING_USE_SMTP=false`, emails get status `staging_captured` immediately (visible in `/staging-mail`).

## UI Standards

- **All tables are client-side sortable**: The global JS in `layout.blade.php` makes every `table.table thead th` clickable to sort. Column index is computed per-table (not global). Use `data-no-sort` on a `<th>` to opt out.
- **Photo uploads**: Accept images, videos (MP4/MOV/AVI/WebM), and ZIP archives. Max 100MB per file. ZIP extraction skips `__MACOSX`. Dedup via `xxh3` file hash. Quality scoring + face detection run on images.
- **Forms with dynamic options**: Use "Add option" buttons with max limits, preset buttons for common patterns (Yes/No/Abstain). Remove buttons (✕) on all but the minimum required items.
