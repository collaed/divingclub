## medical.md — Medical Compliance System

## Overview

Diving requires valid medical certificates. Rules vary by federation, age, and certification level. The system enforces compliance at event registration time and sends automated expiry reminders.

## Data Model

- `medical_compliance_rules` — per-federation rules: max_age_without_cert, cert_validity_months, age_brackets (JSON)
- `documents` — uploaded personal documents (see **File storage** below). A
  medical certificate is a row with `category = 'medical'`; the important
  columns are `file_path`, `original_filename`, `mime_type`, `size_bytes`,
  `date_established`, `expiry_date`, `is_verified` / `verified_by` /
  `verified_at`, `is_current`, `superseded_by`, `is_compliant`,
  `compliance_notes`, and `reminder_{30,15,7,0}_sent_at`. `SoftDeletes` +
  `Auditable`.
- `member_licences` — federation membership with expiry_date

## File storage

| | |
|---|---|
| Disk | `local` — root `storage_path('app/private')` (a symlink to `/mnt/data/<env>/pics/private/` on the servers). **Not** under `public/`, so not web-served. |
| Path | `documents/{user_id}/{filename}` |
| Filename | `Str::slug("{last_name} {first_name} {cert_type ?? category} {date}").{ext}` — e.g. `kraemer-roger-medical-2026-01-15.pdf`. `{date}` is the form's `date_established` or today; `{ext}` is the uploaded file's client extension (fallback `pdf`). Deterministic → re-uploading for the same person/type/date **overwrites the previous file** (a new `documents` row is still created). |
| Legacy certs | `storage/app/private/medical/` (→ `/mnt/data/<env>/pics/private/medical/`), imported files with no `documents` row, in per-member subdirectories. |
| Serving | Only via `ProfileDocumentController::download()` / `view()` — `auth` + `verified.email` + `abort` unless the viewer owns the doc or `isBureau()`. The framework `serve => true` route (`GET /storage/{path}`) requires a signed URL for this private disk and the app never mints one, so it returns 404 (prod) for these files. |
| At rest | Files are `clubcep:clubcep`, mode ~0664/0775, **not encrypted**. Health data (GDPR Art. 9) — see `gdpr.md`. |

## Federation Rules (examples)

| Federation | Under 40 | 40+ | Cert Validity |
|-----------|----------|-----|---------------|
| FFESSM | No cert needed if recent sport exam | Annual cert required | 12 months |
| LIFRAS | Annual cert always | Annual cert always | 12 months |
| PADI | No cert requirement (waiver-based) | No cert requirement | N/A |
| CMAS | Follows national federation rules | Follows national federation rules | Varies |

Age brackets in `medical_compliance_rules.age_brackets` JSON:
```json
[
  {"min_age": 0, "max_age": 40, "validity_months": 36},
  {"min_age": 40, "max_age": 999, "validity_months": 12}
]
```

## MedicalComplianceService

### `isCompliant(User $user, ?Carbon $atDate): bool`

1. Find user's primary federation (from `member_licences`)
2. Load the `medical_compliance_rules` for that federation
3. Determine age bracket at $atDate
4. Check if user has a valid medical document (`documents.category = 'medical'`) with `date_established + validity_months > $atDate`
5. Return true/false

### `getStatus(User $user, ?Carbon $atDate): array`

Returns: {compliant, expires_at, days_remaining, federation, rule_source}

### `evaluateCertificate(Document $document): void`

Called when a medical cert is uploaded:
- Determines establishment date (from form input or OCR)
- Calculates expiry based on federation rules
- Updates compliance status
- Dispatches `OcrMedicalCert` job if no date_established provided

## Event Registration Gate

In `EventController::register()`:
```php
if (in_array($event->event_type, ['pool', 'dive', 'training'])) {
    if (!app(MedicalComplianceService::class)->isCompliant($targetUser, $event->event_date)) {
        return back()->with('error', __('Medical certificate required.'));
    }
}
```

Social, theory, and long_trip events do NOT require medical compliance.

## Automated Reminders

`SendMedicalReminders` job (daily at 08:00):
- Queries members with medical certs expiring in 30, 15, 7, or 0 days
- Sends reminder email at each threshold
- Logs sends to prevent duplicate reminders

## Document Upload Flow

1. Member (or a bureau member, via `target_user_id`) POSTs a PDF/JPEG/PNG
   (`max:10240` KB, `category in certification,medical,insurance,other`) to
   `POST /profile/document`.
2. `ProfileDocumentController::upload()` — `storeAs('documents/{user_id}', …, 'local')`,
   creates the `Document` row with `is_current = true`.
3. For `category = 'medical'`: `MedicalComplianceService::evaluateCertificate()`
   sets `expiry_date` / `is_compliant` from the federation rule.
4. If no `date_established` was supplied: dispatch `OcrMedicalCert` (background
   OCR to detect the establishment date, then re-evaluate).
5. Plain-text (`Mail::raw`) notification with member name + cert type + date to
   `bureau_master` + `bureau_technical`.

### Bureau verification

`POST /profile/document/{document}/verify` (bureau only) sets `is_verified`,
`verified_by`, `verified_at`, optionally corrects `date_established` /
`cert_type`, and re-runs `evaluateCertificate()`.

### Federation export — `Admin\MedicalExportController`

| Route | Output |
|-------|--------|
| `GET /admin/medical-export` (`exportList`) | CSV of members + `date_established` of their current medical doc, for federation submission (semicolon-separated, BOM, FR headers). Optional `?federation_id=`. |
| `GET /admin/medical-certificates` (`downloadCertificates`) | ZIP of the actual files: DB-tracked current medical docs **plus** legacy files from `private/medical/` that have no DB match. Entries named `"{LASTNAME} {Firstname} {member_id} {TYPE}.{ext}"`. |

## Known limitations

- **`is_current` is never demoted** — `upload()` never sets the previous
  medical doc's `is_current = false` / `superseded_by`, so a member can have
  several "current" medical docs; compliance and the export use an arbitrary
  `->first()`.
- **`downloadCertificates` currently 500s** — `glob("{private/medical}/*")`
  returns the legacy per-member sub-directories and `ZipArchive::addFile()` on a
  directory throws `Read error: Is a directory`. Needs an `is_file()` guard.
- Deterministic filenames overwrite silently (see **File storage**).

## OCR Processing

`OcrMedicalCert` job:
- Extracts text from PDF/image
- Attempts to parse establishment date from common medical cert formats
- Updates `documents.date_established` if found
- Re-evaluates compliance

## Bureau Worklist

Dashboard shows:
- Members with expired medical certs
- Members with certs expiring within 30 days
- Members with no cert on file who are registered for upcoming dive events
