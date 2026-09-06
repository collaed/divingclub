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
| Git | `main` @ deployed commit (no fetch — no deploy key) | `main` @ deployed commit (no fetch — no deploy key) |

\* the running staging app uses its own DB; test suite uses `divingclub_test`.

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

## Deploy Flow (No Git Deploy Key)

The GitHub deploy key is not configured on the server, and the server cannot
`git fetch`/`pull`. Deploy by pushing the committed branch state from a local
clean checkout via `git archive` + `rsync` (excluding `.env`, `storage`,
`vendor`, `node_modules`, `.git`, `bootstrap/cache`), then chown to `clubcep`,
chmod build assets, run migrations + idempotent seeders as `clubcep`, and clear
caches as `clubcep`. For a single file, `scp` works too:

```bash
scp path/to/file root@204.168.168.60:/opt/deploy/apps/divingclub-prod/path/to/file
ssh root@204.168.168.60 "sudo -u clubcep php /opt/deploy/apps/divingclub-prod/artisan view:clear && sudo -u clubcep php /opt/deploy/apps/divingclub-prod/artisan optimize:clear"
```

## Known Issues

- `barryvdh/laravel-debugbar` is in `require-dev` but not installed on server (`--no-dev`). Running `php artisan optimize:clear` as root previously triggered "Class not found" — this is fixed by always running artisan as `clubcep`.
- Disk usage is at 90% — monitor and clean old backups/logs if needed.
- SonarCloud CI check fails with "Automatic Analysis enabled" — a project-side
  config conflict, not a code defect; not a merge blocker (lint/test/build gate).
