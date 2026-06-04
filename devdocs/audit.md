## audit.md — Audit Logging

## Overview

Automatic change tracking via the `Auditable` trait. Records who changed what, with old/new values as JSON. Tracks impersonation. Admin UI for browsing, filtering, exporting, and purging with configurable retention.

## Data Model

### `audit_logs` Table

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint | PK |
| `user_id` | bigint | Who made the change |
| `impersonated_user_id` | bigint | If acting via impersonation, the original admin |
| `action` | varchar | `created`, `updated`, `deleted`, `gdpr_erasure` |
| `model_type` | varchar | Full class name (e.g. `App\Models\User`) |
| `model_id` | bigint | ID of the affected record |
| `old_values` | json | Previous field values (null for create) |
| `new_values` | json | New field values (null for delete) |
| `ip_address` | varchar(45) | Request IP |
| `user_agent` | text | Browser user-agent string |
| `created_at` | timestamp | When the change happened |

**Note**: `$timestamps = false` on the model — only `created_at` is set manually, no `updated_at`.

## Auditable Trait (`App\Traits\Auditable`)

Applied to models that need change tracking via `use Auditable;`.

### Boot Method

Listens to three Eloquent events on the model:

| Event | old_values | new_values |
|-------|-----------|------------|
| `created` | null | all attributes |
| `updated` | original values of changed fields only | changed fields only |
| `deleted` | all original attributes | null |

### Conditions
- Skips if `$auditingDisabled = true`
- Skips if no authenticated user (`auth()->check()`)
- Skips `updated` if no actual changes (`getChanges()` empty)

### Impersonation Tracking
If `session('impersonating')` is set (admin impersonating a member), the session value is stored in `impersonated_user_id`. This allows distinguishing "admin did this as themselves" from "admin did this while impersonating member X".

### Disabling Auditing
```php
// Temporarily disable for bulk operations
User::disableAuditing();
// ... bulk import ...
User::enableAuditing();

// Or with auto-restore
User::withoutAuditing(function () {
    // ... bulk operations ...
});
```

## Controller: `Admin\AuditLogController`

| Method | Route | Purpose |
|--------|-------|---------|
| `index` | `GET /admin/audit-logs` | Filterable list (user, model, action, date range) |
| `show` | `GET /admin/audit-logs/{id}` | Detail view with full JSON diff |
| `purge` | `POST /admin/audit-logs/purge` | Delete entries older than N years |
| `updateRetention` | `POST /admin/audit-logs/retention` | Set retention policy (months) |
| `export` | `GET /admin/audit-logs/export` | CSV stream download |

### Filters
- By user (user_id)
- By model type (ILIKE search)
- By action (created/updated/deleted)
- By date range (from/to)
- Sortable columns: created_at, action, model_type

### Retention
- Configurable via `theme_settings` key `audit_retention_months` (default 24)
- `PurgeAuditLogs` job runs monthly, deletes entries older than retention period
- Manual purge available via admin UI (by years: 1–5)

### Export
Streamed CSV with chunked queries (500 per batch). Columns: Time, User, Action, Model, Model ID, IP, Old Values, New Values.

## Usage Pattern

Models that use the trait:
- `User`, `MemberDetail`, `Event`, `Equipment`, `EquipmentLoan`
- `Article`, `Vote`, `Newsletter`, `LibraryFile`
- Any model where bureau needs an audit trail

## AuditLog::create Convention

When manually creating audit entries (e.g., GDPR erasure), always include `'created_at' => now()` since the model has `$timestamps = false`.
