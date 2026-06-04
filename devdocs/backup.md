## backup.md — Backup System

## Overview

Full application backups (database + storage files) with admin UI for create/inspect/download/delete. Uses spatie/laravel-backup under the hood. Weekly automated backup with 4-backup retention. Optional offsite SFTP upload.

## Architecture

- **Service**: `App\Services\BackupService` — wraps spatie backup command, manages archive lifecycle
- **Controller**: `Admin\BackupController` — admin UI for manual operations
- **Job**: `WeeklyBackup` — scheduled job for automated backups
- **Storage**: `storage/app/backups/` — local backup archive directory

No dedicated database table — backups are filesystem-based (zip archives).

## `BackupService` Methods

| Method | Purpose |
|--------|---------|
| `create(bool $includeFiles = true)` | Run spatie backup, move zip to backups dir, optionally upload offsite |
| `list()` | List all backup archives with size, date, and parsed manifest |
| `readManifest(string $path)` | Extract `manifest.json` from inside a zip/tar.gz |
| `listFiles(string $path)` | List files inside a backup's storage directories |
| `extractFile(string $backupPath, string $filePath)` | Extract a single file from archive |
| `delete(string $filename)` | Delete a backup file (path-traversal safe) |
| `prune(int $keep = 4)` | Remove old backups, keeping the N most recent |

## Manifest

Each backup contains a `manifest.json` with:
```json
{
  "version": "1.0",
  "created_at": "2026-06-01T03:00:00+02:00",
  "driver": "mysql",
  "database": "divingclub",
  "tables": {"users": 85, "events": 342, ...},
  "total_rows": 12450,
  "includes_files": true,
  "storage_files": 1204,
  "storage_size": 524288000,
  "storage_size_human": "500 MB",
  "php_version": "8.3.12",
  "laravel_version": "12.x"
}
```

## Admin UI (`Admin\BackupController`)

| Action | Route | Purpose |
|--------|-------|---------|
| List | `GET /admin/backups` | Show all backups with size, date, manifest summary |
| Create | `POST /admin/backups/create` | Trigger manual full backup |
| Inspect | `GET /admin/backups/{filename}/inspect` | View manifest + file listing |
| Download | `GET /admin/backups/{filename}/download` | Download the zip file |
| Delete | `DELETE /admin/backups/{filename}` | Delete a backup |

## Scheduled Backup

**`WeeklyBackup` job** — runs Sunday at 03:00 via `routes/console.php`:
1. Calls `BackupService::create(includeFiles: true)`
2. Calls `BackupService::prune(4)` — retains last 4 backups
3. Logs result to `schedule_heartbeats`

## Offsite Upload (SFTP)

If `config('backup.offsite_host')` is set, each backup is automatically uploaded via SFTP after creation.

Configuration:
```env
BACKUP_OFFSITE_HOST=backup.example.com
BACKUP_OFFSITE_USER=dcms-backup
BACKUP_OFFSITE_KEY=/home/clubcep/.ssh/backup_key
BACKUP_OFFSITE_DIR=backups
```

Remote filename format: `dcms-bkp-{domain}-{date}.tar.gz`

## Database Support

Works with MySQL, PostgreSQL, and SQLite — database dump handled by spatie/laravel-backup which supports all three. The manifest captures which driver was used.

## Security

- `delete()` validates the file is within `$backupDir` (prevents path traversal)
- Download route requires bureau role
- No public access to backup files
