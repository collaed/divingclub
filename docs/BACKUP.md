# Backup & restore

## What a backup contains

`BackupService::create()` wraps `spatie/laravel-backup`. Each run produces one
zip in `storage/app/backups/` (on the servers that path resolves to
`/mnt/data/<env>/backup/`), named `backup-YYYY-MM-DD-His.zip`, holding:

- `db-dumps/postgresql-<db>.sql.gz` — full database dump
- `private/…` and `public/…` — the stored-file trees, minus the exclusions below
- `manifest.json` — row counts per table, file count / size, versions

**Included files** (`config/backup.php` → `backup.source.files`):

| Tree | In backup? | Notes |
|---|---|---|
| `private/documents`, `private/medical`, `private/scancards`, `private/members` | ✅ | member documents, medical certificates — irreplaceable, ~0.45 GB |
| `public/articles`, `public/avatars`, `public/dive-sites`, `public/newsletters` | ✅ | small, ~20 MB |
| `public/library` (~1.9 GB), `public/event-photos` (~0.65 GB), `public/photos`, `public/images` | ❌ excluded | bulky media — see *Offsite* below |
| `private/thumbnails` | ❌ excluded | regenerable |

> `follow_links => true` is required: on the servers `storage/app/{public,private}/*`
> are symlinks to `/mnt/data/.../pics`. Without it a "with files" backup captures
> only empty directory stubs (the 2026-09-08 finding).

Retention: `BACKUP_RETENTION` (default 4) — `BackupService::prune()` keeps the N
newest. Schedule: weekly, `routes/console.php` (Sun 03:00) via the `clubcep`
scheduler cron.

## Restore

**Take a fresh backup first.** Then, on the target server:

```bash
cd /opt/deploy/apps/divingclub-prod
sudo -u clubcep php8.3 artisan down          # optional: maintenance mode
sudo -u clubcep php8.3 artisan backup:restore backup-2026-09-08-120000.zip
sudo -u clubcep php8.3 artisan optimize:clear
sudo -u clubcep php8.3 artisan up
```

`backup:restore` (see `app/Console/Commands/BackupRestore.php`):

- accepts an absolute path or a filename under `storage/app/backups/`
- prints the manifest, then asks to confirm (skip with `--force`)
- replays the SQL dump with `psql -v ON_ERROR_STOP=1` (pg) / `mysql` (mysql)
- `rsync -a` (no `--delete`) the `private/` and `public/` trees back under
  `storage/app/` — a partial archive never wipes files it didn't contain
- `--only-db` / `--only-files` to restore just one half

Manual equivalent, if the command is unavailable:

```bash
mkdir /tmp/restore && cd /tmp/restore && unzip /path/to/backup-*.zip
gunzip -c db-dumps/postgresql-*.sql.gz | PGPASSWORD=… psql -h 127.0.0.1 -U divingclub_prod -d divingclub_prod -v ON_ERROR_STOP=1
rsync -a private/ /opt/deploy/apps/divingclub-prod/storage/app/private/
rsync -a public/  /opt/deploy/apps/divingclub-prod/storage/app/public/
```

## Offsite copy — Google Drive via rclone

Backups live on `/mnt/data` (same box). The offsite copy goes to **Google Drive**
via `rclone`, driven by `deploy/gdrive-offsite.sh` (installed as
`/opt/deploy/gdrive-offsite.sh`, run daily from the `clubcep` crontab).

> **Not a service account.** `clubcep@gmail.com` is a consumer account — no
> Shared Drives, and a service account has no Drive quota of its own, so its
> uploads to a shared My-Drive folder fail. We use **OAuth as the shared
> `clubcep@gmail.com` account** with the club's own OAuth client (project
> `cep-prod-507014`), Publishing status **In production** so the refresh token
> does not expire.

### One-time setup

```bash
# on prod (rclone is already installed):
sudo -u clubcep rclone config
#   n → name: gdrive → storage: drive
#   client_id / client_secret: <the club OAuth client, Desktop-app type>
#   scope: 1 (drive)
#   service_account_file: (blank)
#   Edit advanced config: n
#   Use web browser to authenticate: n   → prints an `rclone authorize` command
# run that command on a laptop with a browser, sign in as clubcep@gmail.com,
# paste the token blob back into the prompt.
#   Configure as Shared Drive: n
```

The token lands in `/home/clubcep/.config/rclone/rclone.conf` (chmod 600).
Revoke anytime at myaccount.google.com → Security → Third-party access.

### What the script does (`deploy/gdrive-offsite.sh`)

1. `rclone copy` new `backup-*.zip` → `gdrive:DCMS/prod/backups`
2. prune the remote to the **2 newest** zips (local keeps `BACKUP_RETENTION`=4)
3. `rclone sync` `/mnt/data/prod/pics/public` → `gdrive:DCMS/prod/pics-public`
   — mirror of the bulky media the zip excludes; incremental after the first
   ~2.7 GB run
4. push a Kuma heartbeat (pass the monitor token as `$1`)

Cron (`clubcep`), after the nightly DB backup:
```
45 3 * * * /opt/deploy/gdrive-offsite.sh <kuma-push-token> >> /var/log/gdrive-offsite.log 2>&1
```

Restore from Drive: `rclone copy gdrive:DCMS/prod/backups/backup-….zip /tmp/` then
`php artisan backup:restore /tmp/backup-….zip`; pics with `rclone copy
gdrive:DCMS/prod/pics-public <target>`.
