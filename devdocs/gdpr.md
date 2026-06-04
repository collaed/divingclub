## gdpr.md — GDPR & Privacy Compliance

## Overview

Three GDPR rights implemented: consent management, data portability (JSON export), and right to erasure (anonymization). Member-facing controller at `/privacy/*`.

## Data Model

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `gdpr_consents` | Per-user consent records | user_id, consent_type, granted (bool), granted_at, revoked_at |

## Consent Types (3)

| Type | Purpose |
|------|---------|
| `data_processing` | Required for membership — processing personal data for club operations |
| `marketing` | Optional — receiving promotional emails, newsletters |
| `photo_publication` | Optional — publishing photos/videos containing the member |

## Controller: `GdprController`

| Method | Route | Purpose |
|--------|-------|---------|
| `consents` | `GET /privacy/consents` | View current consent status |
| `updateConsent` | `POST /privacy/consents` | Toggle a consent type |
| `exportData` | `GET /privacy/export` | Download all personal data as JSON |
| `requestErasure` | `GET /privacy/erasure` | Confirmation page for erasure |
| `confirmErasure` | `POST /privacy/erasure` | Execute erasure (requires password) |

## Consent Update

Uses `updateOrCreate` on `(user_id, consent_type)`:
- `granted = true` → sets `granted_at = now()`, clears `revoked_at`
- `granted = false` → sets `revoked_at = now()`, clears `granted_at`

## Data Export (Article 20 — Portability)

Exports a JSON file containing all user data:

```json
{
  "user": {"id", "username", "primary_email", "created_at"},
  "detail": {full member_details record},
  "emails": [secondary emails],
  "licences": [federation memberships],
  "documents": [{"category", "original_filename", "date_established", "created_at"}],
  "consents": [gdpr_consents records],
  "event_registrations": [{"event", "event_date", "status", "registered_at"}],
  "payments": [{"type", "season_year", "amount_due", "amount_paid", "status", "communication"}],
  "exported_at": "ISO8601 timestamp"
}
```

Served as attachment download: `gdpr-export-{user_id}-{date}.json`

## Right to Erasure (Article 17)

### Preconditions
- User must confirm via checkbox (`confirm = accepted`)
- User must provide current password (`current_password` validation)

### Erasure Steps (in order)

1. **Delete all documents**: iterate user's documents, delete physical files from `Storage::disk('local')`, then delete DB records
2. **Delete avatar**: remove from `Storage::disk('public')`
3. **Anonymize member_details**: set personal fields to `'ERASED'` or null:
   - `first_name` → "ERASED", `last_name` → "ERASED"
   - Nulled: birth_name, all phones, date_of_birth, place_of_birth, address fields, emergency contacts, avatar_path
4. **Anonymize user**: `primary_email` → `erased-{id}@erased.local`, `password` → null, `username` → null
5. **Delete secondary emails**: `user_emails` records removed
6. **Delete OAuth accounts**: `user_social_accounts` records removed
7. **Audit log**: create entry with `action = 'gdpr_erasure'`, records erased_at timestamp
8. **Logout**: force session termination

### What is NOT deleted
- Event registrations (retained for club records, but person is now anonymous)
- Payment records (retained for accounting, person anonymized)
- Audit log entry (proof of erasure for compliance)
- The user record itself (soft-anonymized, not hard-deleted)

## Model: `GdprConsent`

- Fillable: user_id, consent_type, granted, granted_at, revoked_at
- Casts: `granted` → boolean, `granted_at`/`revoked_at` → datetime
- Relationship: `user()` → BelongsTo User
- User relationship: `User::gdprConsents()` → HasMany GdprConsent
