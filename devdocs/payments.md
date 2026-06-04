## payments.md — Payments & Fee Calculation

## Fee Structure

Annual membership fees computed by `FeeCalculationService::calculate()`.

### Formula
```
total = base_amount × status_modifier × age_discount + Σ(optional_components)
```

### Inputs
- `membership_fees` table: base amounts per season_year × status_slug
- `membership_fee_components` table: optional add-ons (federation licence, insurance, pool access)
- Member's status (membre_de_droit, junior, famille → different rates)
- Member's age (junior discount, senior discount)

### Output
- `payment_expected` record: user_id, type='membership', amount_due, communication (structured SEPA reference), components (JSON breakdown), status

## Payment Statuses

`payment_expected.status`: `pending` → `partial` → `paid`

Updated by bank reconciliation or manual marking.

## Fee Components (`membership_fee_components`)

| Column | Type | Purpose |
|--------|------|---------|
| `season_id` | FK | Which season this applies to |
| `name` | varchar | Display name (e.g. "FLASSA Licence") |
| `slug` | varchar | Machine identifier |
| `amount` | decimal(8,2) | Cost of this component |
| `is_base` | bool | Whether this is the base fee (not optional) |
| `is_optional` | bool | Whether member can opt in/out |
| `description` | varchar | Explanation shown in calculator |
| `sort_order` | int | Display ordering |

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

## SEPA QR Codes

`QrCodeController` generates QR codes for payments in two modes:

### EPC QR (Legacy) — Direct bank transfer encoding
- Standard EPC format: `BCD\n002\n1\nSCT\n{BIC}\n{name}\n{IBAN}\nEUR{amount}\n\n{communication}`
- Members scan with banking app → pre-filled transfer
- Public QR at `GET /qr/sepa/public?amount=X&communication=Y` (dues calculator)
- Per-payment QR at `GET /qr/sepa/{payment}` (authenticated, own payments or bureau)

### Signed Payment QR (Anti-Quishing)
- Encodes a **signed verification URL** instead of raw bank details
- HMAC-SHA256 signature using `config('app.key')`
- Payload: `{amount}|{communication}|{expires_timestamp}`
- 30-day validity on signature
- Scanning shows a verification page with club bank details + validity confirmation
- Route: `GET /qr/payment/signed?amount=X&communication=Y`
- Verification: `GET /payment/verify?a=X&c=Y&e=timestamp&s=signature`

### Other QR Types
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

## Public Dues Calculator

`/dues-calculator` — unauthenticated page where prospective members can estimate their annual fees by selecting status and optional components.

## Communication Format

SEPA communication: `{CLUB_CODE}-{YEAR}-{USER_ID}-{LASTNAME}`
Example: `CEP-2026-42-COLLART`

Ensures unique, machine-parseable references for reconciliation.
