## payments.md — Payments & Fee Calculation

## Fee Structure

Annual membership dues computed by `FeeCalculationService::calculate()`.

Full specification: `.kiro/specs/membership-dues-calculation/` (requirements.md,
design.md, tasks.md). This section is the developer summary.

### Formula
```
total = ceil(cotisation_list_price × taper_factor)   // club-retained base only
      + price(derived FFESSM licence)                // R1, from status + age
      + effective_price(FLASSA)                       // 10 / 0 / omitted, R5–R7
      + Σ(selected assurance)                         // only if a licence allows
```

Only the club-retained cotisation base is tapered; the derived FFESSM licence and
the assurance options are charged at full price. FLASSA is age-tapered per
component (0 € under 18 at the anchor).

### Derived federation licences (`LicenceResolver`)
`app/Services/LicenceResolver.php` derives, from the member's cotisation status
and age at the shared prise-de-licence anchor:
- **FFESSM band** (federation age bands): `lic_enfant` (< 12), `lic_jeune`
  (12 to < 16), `lic_adulte` (16+, full underwater permissions pending parental
  approval), `lic_aucune` (sympathisant).
- **FLASSA state** (`App\Services\FlassaState`): `Required` (10 €, 18+),
  `IncludedFree` (0 €, present, < 18), `NotApplicable` (absent, sympathisant).
- **assuranceAllowed**: false for sympathisant (no licence → no personal cover).

Licences are `membership_fee_components` rows distinguished by the `kind` column
(`ffessm_licence`, `flassa`, `assurance`, `other`); their selection is derived,
never user-chosen.

### Season taper reference date
`FeeCalculationService::resolveAsOfDate()`:
- absolute freeze `fee_taper_reference_date` (ThemeSetting) wins, else
- `today + dues_cutoff_grace_days` (ThemeSetting, default 0) — the cutoff falls a
  little later than today to leave the bureau processing time.

### Inputs
- `membership_fees` table: cotisation list prices per season_year × status_id
- `membership_fee_components` table: FFESSM licences, FLASSA, assurances (by `kind`)
- Member's status (status_set constrains offered options) and date of birth
- `seasons.fee_taper_tiers` JSON: `[{from:"MM-DD", pct:N}]`

### Output
- `payment_expected` record: user_id, type='membership', amount_due, communication
  (structured reference), components (JSON breakdown incl. `ffessm_licence` and
  `flassa_state`), provisional flag, status

## Payment Statuses

`payment_expected.status`: `pending` → `partial` → `paid`

Updated by bank reconciliation or manual marking.

## Fee Components (`membership_fee_components`)

| Column | Type | Purpose |
|--------|------|---------|
| `season_id` | FK | Which season this applies to |
| `name` | varchar | Display name (e.g. "Licence FLASSA") |
| `slug` | varchar | Machine identifier (e.g. `lic_adulte`, `flassa`, `ass_loisir1`) |
| `kind` | varchar | Discriminator: `ffessm_licence`, `flassa`, `assurance`, `other` |
| `amount` | decimal(8,2) | Cost of this component |
| `is_base` | bool | Whether this is the base fee (not optional) |
| `is_optional` | bool | Whether member can opt in/out (true only for assurances) |
| `prorata_eligible` | bool | Whether the season taper applies (cotisation base only) |
| `taper_below_age` | tinyint | Age threshold for the per-component age taper (FLASSA = 18) |
| `taper_ratio` | decimal(4,3) | Multiplier below the threshold (FLASSA = 0 = free) |
| `age_anchor_date` | date | Shared prise-de-licence anchor for the age taper |
| `description` | varchar | Explanation shown in calculator |
| `sort_order` | int | Display ordering |

Seeded for season 2027 by `database/seeders/Fee2027Seeder.php` (registered in
`CepSeeder`): 6 cotisations (`membership_fees`), 4 FFESSM licences, 1 FLASSA, 7
assurances.

## Bank Transactions (`bank_transactions`)

| Column | Type | Purpose |
|--------|------|---------|
| `transaction_date` | date | When the bank processed it |
| `amount` | decimal(10,2) | Transaction amount |
| `communication` | varchar | SEPA communication/reference |
| `counterparty` | varchar | Sender name |
| `matched_payment_id` | FK | Linked payment_expected (after reconciliation) |
| `match_score` | int | Fuzzy match confidence (0-100) |
| `status` | varchar | unmatched, matched, confirmed, rejected |
| `statement_ref` | varchar(100) | Bank statement reference number |
| `confirmed_by` | FK | Bureau member who confirmed |

## Payment QR Codes — RETIRED (2026-09)

Payment/SEPA/EPC QR codes were removed. Rationale:
- The **EPC069-12** standard is deprecated.
- **Wero** (the EPC successor) is becoming a closed standard not suitable for open QR generation.

Dues payment now relies on the **printed IBAN + BIC + structured communication** shown on the `/dues` page and on event payment panels. Members copy the communication string into their banking app manually.

Removed routes/methods:
- `GET /qr/sepa-public` → `QrCodeController::sepaPublic` (public EPC QR)
- `GET /qr/sepa/{payment}` → `QrCodeController::sepa` (per-payment EPC QR)
- `GET /qr/payment` → `QrCodeController::signedPaymentQr` (signed-URL QR)
- `GET /pay/verify` → `QrCodeController::verifyPayment` (verification landing page)
- `QrCodeController::buildSignedUrl` helper
- Views `resources/views/cotisation.blade.php` and `resources/views/payment-verify.blade.php`

### Remaining QR Types (unchanged)
- **vCard QR** (`GET /qr/vcard`): member's contact card
- **Federation QR** (`GET /qr/federation/{licence}`): licence number QR for dive checks

## Bank Reconciliation

### Import
Bureau uploads CSV/PDF bank statement → `BankReconciliationService::parseStatement()` or `parsePdfStatement()` → creates `bank_transactions` records.

### Matching Algorithm (`suggestMatches()`)
1. Exact match: communication string matches `payment_expected.communication`
2. Fuzzy match: Levenshtein distance on communication + amount match
3. Partial match: amount matches but communication differs slightly

### Confirmation Flow
```
Bureau views suggested matches → clicks Confirm
  → BankReconciliationService::confirmMatch(BankTransaction)
    → Links transaction to payment_expected
    → Updates payment_expected.status = 'paid' (or 'partial')
    → AuditLog entry
```

## Event Deposits

Events can have up to 3 deposit stages:
- `deposit_1_date` + `deposit_1_amount`
- `deposit_2_date` + `deposit_2_amount`
- `deposit_3_date` + `deposit_3_amount`

On registration, `PaymentExpected` auto-created with total of configured deposits.

## Dues Calculator

`/dues` (`dues.show`) — bank-transfer preparation screen. Four option groups:
Cotisation CEP (user-chosen, constrained to the member's status_set), Licence
FFESSM + FLASSA (derived, read-only), Assurance Individuelle (optional, gated by
the derived licence). Guests can preview; authenticated members can commit
(`dues.commit` → `payment_expected`, provisional when unclassified). Live
recompute via `resources/js/dues-live.js` (progressive enhancement; the Calculate
button works without JS). Read-only payment summary (Titulaire/IBAN/BIC/Banque/
Montant/Mention) from `ThemeSetting`.

Legacy alias: `/cotisation` redirects to `dues.show`.

## Communication Format

SEPA communication: `{CLUB_CODE}-{YEAR}-{USER_ID}-{LASTNAME}`
Example: `CEP-2026-42-COLLART`

Ensures unique, machine-parseable references for reconciliation.
