# Deployment & Server Operations

Two ClubCEP apps run on the **same** Hetzner host (`204.168.168.60`): **staging**
(`test.clubcep.eu`) and **production** (`prod.clubcep.eu`). They are separate
app directories, PHP-FPM pools, and PostgreSQL databases. `auth.clubcep.eu`
serves only `/auth/*` for prod (social login) and redirects everything else to
`prod.clubcep.eu`.

Both run PHP-FPM as user `clubcep`. Always run artisan as `clubcep` (see below).

| | Staging | Production |
|---|---------|-----------|
| URL | test.clubcep.eu | prod.clubcep.eu (+ auth.clubcep.eu) |
| App path | `/opt/deploy/apps/divingclub` | `/opt/deploy/apps/divingclub-prod` |
| PHP-FPM pool | `/etc/php/8.3/fpm/pool.d/clubcep.conf` | `/etc/php/8.3/fpm/pool.d/clubcep-prod.conf` |
| PHP-FPM socket | `/run/php/php8.3-fpm.sock` | `/run/php/php8.3-fpm-prod.sock` |
| App user | `clubcep` | `clubcep` |
| DB (pgsql) | `divingclub_test`* | `divingclub_prod` |
| APP_ENV / DEBUG | staging / true | production / false |
| Caddy log | `/var/log/caddy/access.log` | `/var/log/caddy/prod-access.log` |
| Git | tracks `origin/main` (server pulls directly) | tracks `origin/main` (server pulls directly) |
| Auto-deploy | **suspended** since 2026-09-05 (loose-deploy resets wiped changes) | nightly 01:00 via `/opt/deploy/auto-deploy.sh` |
| Scheduler cron | `clubcep` crontab runs `schedule:run` here | needs its **own** `schedule:run` line (see `devdocs/scheduled-tasks.md`) |
| Queue | supervisor program `horizon` (`php artisan horizon`) | supervisor program `horizon-prod` |

\* the running staging app uses its own DB; test suite uses `divingclub_test`.

**PHP:** FPM runs 8.3. The box's default CLI `php` is **8.5**, which is missing
`mbstring`, `bcmath`, `intl` and `opcache` — run cron / artisan / Horizon with
`/usr/bin/php8.3` explicitly, or `apt-get install php8.5-{mbstring,bcmath,intl,opcache}`.

**Redis:** staging and prod share one Redis instance/DB with no `REDIS_DB` or
prefix separation — their queues and Horizon namespaces collide, so a job
dispatched by one env can run on the other's workers. Set a distinct `REDIS_DB`
per environment and restart both Horizons.

**Production safety:** on prod, never run seeders that create members, events,
or other content unconditionally — `CepSeeder` (and its `seedMembers` /
`seedEvents`) is **not** idempotent and would duplicate rows. Only run
idempotent config seeders explicitly (e.g. `MemberStatusSeeder`,
`StatusSetSeeder`, `Fee2027Seeder`, `SystemContentSeeder`). Back up the prod DB
before any migration or seeding. Never touch registrations or certifications
data on prod without an explicit request.

## Cache Operations on Hetzner

- PHP-FPM runs as user `clubcep`. After deleting or clearing cache files on the
  server, **always** fix ownership (adjust the path for staging vs prod):
  ```bash
  # staging
  ssh root@204.168.168.60 "chown -R clubcep:clubcep /opt/deploy/apps/divingclub/bootstrap/cache/ /opt/deploy/apps/divingclub/storage/"
  # production
  ssh root@204.168.168.60 "chown -R clubcep:clubcep /opt/deploy/apps/divingclub-prod/bootstrap/cache/ /opt/deploy/apps/divingclub-prod/storage/"
  ```
- If running artisan commands as `root`, the regenerated cache files will be owned by root and PHP-FPM (running as `clubcep`) will get "Permission denied" → HTTP 500 with empty response body.
- Prefer running artisan commands as the app user:
  ```bash
  ssh root@204.168.168.60 "sudo -u clubcep php /opt/deploy/apps/divingclub/artisan view:clear"       # staging
  ssh root@204.168.168.60 "sudo -u clubcep php /opt/deploy/apps/divingclub-prod/artisan view:clear"  # production
  ```

## Built assets (avoid 403 on hashed bundles)

`scp`/`rsync` preserve local file mode; a restrictive umask can leave
`public/build/assets/*` at `0640`, which the web server cannot read → 403 on the
JS/CSS bundle, silently breaking all Bootstrap JS (dropdowns, accordions, tabs).
After deploying `public/build`, always force world-readable:
```bash
ssh root@204.168.168.60 "find /opt/deploy/apps/<app>/public/build -type f -exec chmod 644 {} + && find /opt/deploy/apps/<app>/public/build -type d -exec chmod 755 {} +"
```

## Deploy Flow

The server pulls from GitHub directly. `/opt/deploy/auto-deploy.sh` (root cron,
01:00) does, per app: `git checkout -- . && git clean -fd`, `git pull origin
main`, and — if HEAD moved — `composer install --no-dev`, `php artisan
package:discover`, `php artisan migrate --force`, `php artisan optimize:clear`,
then `supervisorctl restart horizon horizon-prod`. It currently deploys **prod
only**; the staging line is commented out (see the table above).

Normal release: merge to `main`, then `ssh prod.clubcep.eu /opt/deploy/auto-deploy.sh`
(or wait for 01:00). Run artisan as `clubcep`. `git clean -fd` **discards any
uncommitted working-tree changes on the server** — commit or `git stash` first.

Single file, no full deploy:
```bash
scp path/to/file prod.clubcep.eu:/opt/deploy/apps/divingclub-prod/path/to/file
ssh prod.clubcep.eu "sudo -u clubcep php8.3 /opt/deploy/apps/divingclub-prod/artisan optimize:clear"
```

Committed `public/build` assets are checked out by the pull; run `npm run build`
locally (or on the server) if a change touched `resources/js` or `resources/scss`
and rebuilt bundles were not committed — the `build` npm script skips `vite build`
when `public/build/manifest.json` already exists.

## Known Issues

- `barryvdh/laravel-debugbar` is in `require-dev` but not installed on server (`--no-dev`). Running `php artisan optimize:clear` as root previously triggered "Class not found" — this is fixed by always running artisan as `clubcep`.
- Disk: root FS was flagged at 90% in early 2026; as of Sep 2026 it is ~48%
  (`/mnt/data`, the separate backup mount, ~10%). Still watch `storage/logs` — a
  Horizon crash-loop once filled it with ~750 MB of repeated stack traces.
- SonarCloud CI check fails with "Automatic Analysis enabled" — a project-side
  config conflict, not a code defect; not a merge blocker (lint/test/build gate).
- `github-advanced-security` PR check fails ("model not supported", a Copilot
  agent) — not a code defect, not a merge blocker.
