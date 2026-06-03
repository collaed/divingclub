## medical.md — Medical Compliance System

## Overview

Diving requires valid medical certificates. Rules vary by federation, age, and certification level. The system enforces compliance at event registration time and sends automated expiry reminders.

## Data Model

- `medical_compliance_rules` — per-federation rules: max_age_without_cert, cert_validity_months, age_brackets (JSON)
- `documents` — uploaded medical certificates: category='medical', date_established, file_path
- `member_licences` — federation membership with expiry_date

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

1. Member uploads PDF/image via `/profile/document`
2. `ProfileDocumentController::upload()` stores file, creates `Document` record
3. `MedicalComplianceService::evaluateCertificate()` evaluates validity
4. If no `date_established`: dispatches `OcrMedicalCert` job (background OCR)
5. Email notification sent to bureau (medical upload notification)

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
