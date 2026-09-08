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

Backups currently live only on `/mnt/data` on the same box (`BACKUP_OFFSITE_HOST`
is unset). Recommended second copy using the club's Google account:

1. **One-time (needs a browser once):**
   ```bash
   sudo apt-get install -y rclone
   rclone config    # n → name "gdrive" → drive → leave client id/secret blank
                    # → scope 1 (full) → auto config → sign in as the club account
                    # → optionally point at a Shared Drive
   ```
   Or use a **Google service account** (`rclone config` → `service_account_file`)
   and share a Drive folder with its address — no token expiry, better for a
   headless server.

2. **Nightly copy of the DB+private backups** (small, keep versions):
   ```
   15 3 * * * rclone copy /mnt/data/prod/backup/DivingClub gdrive:DCMS/prod/backups --max-age 8d --transfers 2 >/dev/null 2>&1
   ```

3. **Weekly mirror of the bulky media** that the zip excludes:
   ```
   0 4 * * 0 rclone sync /mnt/data/prod/pics/public gdrive:DCMS/prod/pics-public --fast-list --transfers 4 >/dev/null 2>&1
   ```
   `sync` is incremental — only changed/new files transfer. First run uploads
   ~2.7 GB; subsequent runs are seconds.

4. Feed a Kuma push monitor from each cron line (`&& curl …/api/push/<token>`)
   so a silent failure is visible.

`spatie/laravel-backup` can also write straight to a Google Drive Flysystem disk
(add it to `backup.backup.destination.disks`), but rclone keeps backup delivery
decoupled from the app and covers the pics mirror in the same tool.
