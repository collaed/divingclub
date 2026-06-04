## scheduled-tasks.md — Scheduled Tasks & Jobs

## Overview

All scheduled tasks defined in `routes/console.php`. Laravel's scheduler runs via a single cron entry (`* * * * * php artisan schedule:run`). Each task records a heartbeat in `schedule_heartbeats` for monitoring.

## Schedule

| Frequency | Time | Job/Command | Purpose |
|-----------|------|-------------|---------|
| Daily | 08:00 | `SendMedicalReminders` | Email members with expiring medical certificates (30/15/7/0 days) |
| Daily | 09:00 | `SendEquipmentReminders` | Email members with overdue equipment loans |
| Weekly | Sunday 03:00 | `WeeklyBackup` | Full backup (DB + files), prune to last 4 |
| Hourly | — | `ProcessTranslations` | Translate stale/missing article translations |
| Every minute | — | `AutoOpenCloseVotes` | Open votes at `opens_at`, close at `closes_at` |
| Every minute | — | `PollInboundMail` | Check inbound email for alias routing |
| Monthly | 1st 04:00 | `PurgeAuditLogs` | Retention cleanup of old audit log entries |
| Monthly | 1st 05:00 | `CleanupClassifieds` | Soft-delete expired classified ads (30-day expiry) |
| Every 10 min | — | `sync:old-events` | Sync events from legacy Joomla system |
| Hourly | — | `legacy:sync` | Bidirectional sync with legacy member database |
| Every 10 min | — | `incoming:process` | Process incoming files (uploaded documents, imports) |

## Heartbeat Monitoring

### Table: `schedule_heartbeats`

| Column | Type | Purpose |
|--------|------|---------|
| `task` | varchar(255) | Unique task identifier |
| `last_run_at` | timestamp | When it last completed |
| `success` | bool | Whether the last run succeeded |
| `message` | text | Error message if failed |
| `duration_ms` | int unsigned | Execution time in milliseconds |

Each task calls `ScheduleHeartbeat::beat('task-name')` in its `->after()` callback.

### Heartbeat Names

`medical-reminders`, `weekly-backup`, `translations`, `vote-auto`, `inbound-mail`, `audit-cleanup`, `classifieds-cleanup`, `equipment-reminders`, `joomla-sync`, `legacy-sync-bidi`, `incoming-files`

## Job Classes

### `SendMedicalReminders`
- Finds members with medical certificates expiring in 30/15/7/0 days
- Checks `documents.reminder_*_sent_at` fields to avoid duplicate emails
- Sends email and updates the sent_at field
- Uses `MedicalComplianceService` for rule evaluation

### `WeeklyBackup`
- Calls `BackupService::create(includeFiles: true)`
- Calls `BackupService::prune(4)`
- Offsite upload triggered automatically by BackupService

### `ProcessTranslations`
- Queries articles with stale translations or missing locales
- Calls `ArticleTranslationService::translate()` for each
- Rate-limited by Google Translate API chunking (300ms between requests)

### `AutoOpenCloseVotes`
- Opens: `votes` where `status = 'draft'` and `opens_at <= now()`
- Closes: `votes` where `status = 'open'` and `closes_at <= now()`
- Generates vote tokens when opening (if not already generated)

### `CleanupClassifieds`
- Soft-deletes articles with `article_type = 'classified'` and `expires_at < now()`

### `PurgeAuditLogs`
- Deletes `audit_logs` older than configured retention period (default: 12 months)

### `SendEquipmentReminders`
- Finds `equipment_loans` where `expected_return_date < today` and `returned_at IS NULL`
- Sends reminder email if `reminder_sent_at` is null or older than 7 days

### `PollInboundMail`
- Checks configured mailbox for incoming emails
- Routes to correct handler based on alias (event participant lists, etc.)

## Artisan Commands (Operations & Legacy)

Commands run via `php artisan` directly or scheduled. Auto-registered from `app/Console/Commands/`.

### Photo Management

| Command | Purpose | Usage |
|---------|---------|-------|
| `photos:import {event_id} {directory}` | Import photos from directory into event (Google Takeout exports) | One-off, manual |
| `photos:scan --faces --quality --force` | Detect faces + rescore quality on existing event photos | Manual or batch |

### Document Processing

| Command | Purpose | Usage |
|---------|---------|-------|
| `incoming:process --dry-run` | Match files in `storage/app/incoming/` to members by name, move to profile documents | Every 10 min |

### Inbound Email

| Command | Purpose | Usage |
|---------|---------|-------|
| `mail:inbound --to={recipient} --from={sender}` | Postfix pipe for inbound email routing (aliases: bureau@, event-{id}@, members.s{id}@) | Continuous (Postfix pipe) |

### Legacy Migration & Sync

| Command | Purpose | Usage |
|---------|---------|-------|
| `import:legacy --dry-run --skip-*` | One-time migration from Joomla/Google Calendar (members, events, payments, licences) | Once during migration |
| `import:enrich-events --dry-run` | Post-import enrichment (colors, responsible, max_participants by type pattern) | Once after import |
| `sync:old-events --since= --until=` | Sync events from legacy Joomla system | Every 10 min |
| `legacy:sync --import-only --export-only --dry-run` | Bidirectional sync between legacy Joomla MySQL and new system | Hourly |

## Utility Controllers (minor endpoints)

| Controller | Route | Purpose |
|------------|-------|---------|
| `ContactMemberController` | `GET/POST /members/{user}/contact` | Send message to another member (privacy-safe — doesn't expose email) |
| `HealthController` | `GET /health` | JSON health check (DB connectivity, cache, disk, response time) |
| `AuditorController` | `GET /admin/auditor` | Financial overview dashboard (total due, paid, outstanding, matched transactions) |
| `MedicalExportController` | `GET /admin/medical-export` | CSV/ZIP export of medical data for federation submission |
| `ThumbnailController` | `GET /admin/thumbnails/{file}` | On-demand thumbnail generation for library files (cached) |
| `StagingMailController` | `GET /staging/mailbox` | Staging-only mailbox viewer for captured emails (404 in production) |

## Cron Setup

Single cron entry required:
```cron
* * * * * cd /opt/deploy/apps/divingclub && php artisan schedule:run >> /dev/null 2>&1
```

## Queue Worker

Some jobs dispatch sub-tasks to the queue (email sending, translation chunks). Queue worker should run continuously:
```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

Queue connection: `database` (uses `jobs` table).
