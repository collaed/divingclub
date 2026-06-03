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

## SEPA QR Codes

`QrCodeController` generates EPC QR codes for payments:
- Contains club IBAN, amount, structured communication
- Members scan with banking app → pre-filled transfer
- Public QR at `/qr/sepa/public` (dues calculator)
- Signed QR at `/qr/payment/signed/{token}` (specific payment)

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
