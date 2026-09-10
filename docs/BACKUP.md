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

## Offsite copy — SFTP drop-box on ecb.pm

Backups live on `/mnt/data` on the prod box. The second copy goes over SFTP to
**ecb.pm** (the monitoring host) via `BackupService::offsiteUpload()`, which fires
whenever `BACKUP_OFFSITE_HOST` is set: `sftp -i <key> -b - <user>@<host>`, `cd
<dir>`, `put <zip> dcms-bkp-<domain>-<date>.tar.gz`.

> **Status: designed, not yet activated.** ecb.pm's root disk was at 92% (Sep
> 2026) — reclaim ~13 GB of dangling docker volumes (`docker volume prune`) or
> attach a small Hetzner volume first.

### On ecb.pm — a blind put-only account

```bash
sudo useradd -m -d /home/dcms-backup -s /usr/sbin/nologin -g backup dcms-backup
sudo chown root:root /home/dcms-backup && sudo chmod 755 /home/dcms-backup   # chroot needs root-owned
sudo install -d -o root -g root -m 755 /home/dcms-backup/.ssh
sudo install -d -o dcms-backup -g backup -m 0300 /home/dcms-backup/upload    # write+traverse, NOT readable → cannot ls/get/rm
# prod's public key:
echo 'ssh-ed25519 AAAA… clubcep@prod' | sudo tee /home/dcms-backup/.ssh/authorized_keys
sudo chown root:root /home/dcms-backup/.ssh/authorized_keys && sudo chmod 644 /home/dcms-backup/.ssh/authorized_keys
```

`/etc/ssh/sshd_config.d/dcms-backup.conf`:
```
Match User dcms-backup
    ChrootDirectory /home/dcms-backup
    ForceCommand internal-sftp
    AllowTcpForwarding no
    X11Forwarding no
    PermitTunnel no
    AuthorizedKeysFile /home/dcms-backup/.ssh/authorized_keys
```
`sudo sshd -t && sudo systemctl reload ssh`. The account can `cd upload && put`
but the `0300` dir means it cannot list, download, or delete anything — a true
drop-box.

### Retention + freshness cron on ecb.pm

Runs as root (the account itself can't manage the dir). Keep the **2 newest**,
alert Kuma if the newest is stale (weekly backup ⇒ threshold 8 days):
```bash
#!/bin/bash
D=/home/dcms-backup/upload
ls -t "$D"/dcms-bkp-* 2>/dev/null | tail -n +3 | xargs -r rm -f
NEW=$(find "$D" -name 'dcms-bkp-*' -mtime -8 | head -1)
curl -fsS -m10 "https://kuma.ecb.pm/api/push/<offsite-token>?status=$([ -n "$NEW" ] && echo up || echo down)&msg=$([ -n "$NEW" ] && echo fresh || echo stale)" >/dev/null 2>&1
```
`30 7 * * * /opt/scripts/dcms-offsite-prune.sh` — create a Kuma push monitor
"PROD offsite backup" and drop its token in.

### On prod

```bash
sudo -u clubcep ssh-keygen -t ed25519 -N '' -f /home/clubcep/.ssh/backup_key
# put backup_key.pub into ecb.pm:/home/dcms-backup/.ssh/authorized_keys
sudo -u clubcep ssh -i /home/clubcep/.ssh/backup_key -o StrictHostKeyChecking=accept-new dcms-backup@<ecb.pm host> true  # seed known_hosts
```
Then in prod `.env` (no config cache — `optimize:clear` after):
```
BACKUP_OFFSITE_HOST=<ecb.pm host>
BACKUP_OFFSITE_USER=dcms-backup
BACKUP_OFFSITE_KEY=/home/clubcep/.ssh/backup_key
BACKUP_OFFSITE_DIR=upload
```

### Known limitations

- Upload runs synchronously inside the backup (fine for the weekly job; a manual
  UI backup blocks for the ~0.5 GB transfer).
- Remote file is named `*.tar.gz` but is the `.zip` — cosmetic.
- The bulky `public/*` media (~2.7 GB) is **not** in the backup zip and so not in
  this offsite copy. Mirror it separately if it matters.

## Deferred — Google Drive via rclone

Set aside (`clubcep@gmail.com` is consumer Gmail → no Shared Drives, and a
service account has no Drive quota → its uploads to a shared folder fail; the way
in is OAuth as the shared account). The ready script is **`deploy/gdrive-offsite.sh`**
— daily `rclone copy` of `backup-*.zip` to `gdrive:DCMS/prod/backups` (kept to the
2 newest) + `rclone sync` of the pics, with a Kuma push. Revisit with:
`sudo -u clubcep rclone config` (drive → client_id/secret → scope 1 → no service
account → run the printed `rclone authorize` on a laptop, sign in as
`clubcep@gmail.com`, paste the token back).

## Deferred — Google Drive via rclone

Set aside for now (see above). The ready-to-use pieces are kept for when it's
revisited:

- **`deploy/gdrive-offsite.sh`** — daily `rclone copy` of `backup-*.zip` to
  `gdrive:DCMS/prod/backups` (pruned to the 2 newest on the remote) + `rclone
  sync` of `/mnt/data/prod/pics/public`, with a Kuma push.
- Blocked on: `clubcep@gmail.com` is **consumer** Gmail → no Shared Drives, and a
  service account has no Drive quota, so its uploads to a shared My-Drive folder
  fail. The path forward is **OAuth as the shared `clubcep@gmail.com` account**
  with the club's own OAuth client (project `cep-prod-507014`, Desktop-app type,
  consent screen "In production" so the refresh token doesn't expire):
  ```bash
  sudo -u clubcep rclone config      # n → gdrive → drive → client_id/secret →
                                     # scope 1 → no service account → no browser
                                     # → run the printed `rclone authorize` on a
                                     # laptop, sign in as clubcep@gmail.com, paste
                                     # the token back → not a Shared Drive
  ```
  Then install the script to `/opt/deploy/gdrive-offsite.sh` and cron it
  (`45 3 * * * … <kuma-token>`).
