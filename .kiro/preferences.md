# Project Preferences & Patterns

## Developer
- Eddy Collart — solo developer, Bureau Master of Club Européen de Plongée (CEP), Luxembourg
- Native French speaker, code/comments in English

## Code Philosophy
- Absolute minimal code — no verbose implementations, no over-engineering
- Fix the root cause, not symptoms
- Reuse existing components/traits before creating new ones
- No unnecessary abstractions — keep it Laravel-conventional

## Workflow
1. Edit locally (`/home/collaed/laravel/divingclub`)
2. `vendor/bin/pint --dirty --format agent`
3. `php artisan test --compact` (filter when possible)
4. `git add -A && git commit && git push origin main`
5. SSH to Hetzner: `su - deploy -c "cd /opt/deploy/apps/divingclub && git pull"` then `php artisan optimize:clear`
6. Quick smoke test: `curl -s -u cep:cep2026 -o /dev/null -w "%{http_code}" http://204.168.168.60/`

## Infrastructure
- **Local**: MySQL, PHP 8.3, `APP_DEBUG=true`
- **Staging (Hetzner)**: `204.168.168.60`, PostgreSQL 16, Caddy, `APP_DEBUG=false`, `MAIL_MAILER=log`
- SSH: `ssh root@204.168.168.60` (key auth, no sudo needed)
- App path on Hetzner: `/opt/deploy/apps/divingclub`
- Code must be compatible with both MySQL and PostgreSQL (no `RAND()` vs `RANDOM()` issues, etc.)
- Staging basic auth: `cep` / `cep2026`

## Domain & DNS
- Domain: `clubcep.eu` — registrar Namecheap, NS via topdns.com
- Target: `laravel.clubcep.eu` → `204.168.168.60`
- HTTPS via Caddy once DNS propagates

## Testing
- Don't add tests unless explicitly asked
- Don't remove existing tests without approval
- Run minimal filtered tests after changes, full suite only when asked
- Staging has no real email — `MAIL_MAILER=log`

## UI/UX Patterns Established
- Bootstrap 5 + Blade (no Livewire, no Inertia, no React)
- `<x-per-page>` component for list pagination (30/50/100/All)
- `<x-sortable-th>` component for clickable column headers
- `PaginatesFromRequest` trait on controllers with lists
- Drag-and-drop homepage widget system with per-widget config (⚙), visibility, and zone-adaptive rendering
- Forms redirect back to edit page after save (not to index)
- Admin viewing another user's profile must pass `target_user_id` in forms

## Naming & Style
- Commit messages: `fix:`, `feat:`, `chore:` prefix, concise summary, optional bullet list body
- French content in seeds/fixtures, English in code
- 11 locales supported — translations via `__()` helper everywhere

## Don't
- Don't create documentation files unless explicitly asked
- Don't create verification scripts when tests cover the functionality
- Don't change dependencies without approval
- Don't use `env()` outside config files
- Don't use `DB::` — prefer `Model::query()`
- Don't add OAuth providers without credentials configured
- Don't bold text in chat responses
