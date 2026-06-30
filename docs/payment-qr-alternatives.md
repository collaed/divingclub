# Payment QR Alternatives — Design Notes

## Status: Parked (branch `feature/payment-qr-alternatives`)

## Problem

EPC QR codes (SEPA Credit Transfer QR — "scan to pay" directly in banking apps) are being phased out or restricted by banks due to **quishing** (QR phishing). Attackers print fake QR codes over legitimate ones, redirecting payments to rogue IBANs.

**Wero** (European Payments Initiative) is the designated successor but requires a commercial agreement and per-transaction fees — not viable for a small club.

## Current Implementation (main branch)

The app already mitigated quishing by switching from raw EPC QR to a **signed URL approach**:

1. QR encodes a URL (not bank details) → `https://yourclub.lu/pay/verify?a=180&c=CEP-2026-SMITH&e=...&s=HMAC`
2. User scans → lands on the club's HTTPS page (TLS proves identity)
3. Page shows payment details (IBAN, amount, communication) for manual entry
4. HMAC signature prevents URL tampering, 30-day expiry

This is already safer than raw EPC, but it still requires the member to manually type IBAN + communication into their banking app.

## Alternatives Explored

### 1. Structured Payment Reference (current best option — no integration needed)

Most Luxembourg/EU banking apps allow **pre-filling a payment** if you provide details in a recognized format. The key insight: the member doesn't need a QR at all if the payment details are **copy-paste friendly**.

**Approach:**
- Display payment details with individual "copy" buttons (IBAN, amount, communication)
- Use the structured communication format: `+++123/4567/89012+++` (Belgian/Lux format)
- Some banking apps recognize this format when pasted into the communication field

**Effort:** Minimal — just UX improvement on the existing verification page.

### 2. Deep Links to Banking Apps (research needed)

Some banks support URL schemes to pre-fill transfers:
- **Payconiq by Bancontact** — QR-based, but requires merchant agreement
- **Apple Pay / Google Pay** — not for bank transfers
- **Banking app deep links** — bank-specific, no standard exists

Luxembourg banks (BCEE, BIL, BGL, Raiffeisen, ING) don't publish deep link schemas.

**Verdict:** Not viable without per-bank agreements.

### 3. Request-to-Pay (SEPA R2P / SRTP)

EU standard for sending payment requests. The payer's bank presents the request and they approve.
- Requires the club to have a PSP (Payment Service Provider) that supports R2P
- Monthly fees + per-request fees
- Not yet widely supported by consumer banking apps in Luxembourg (2026)

**Verdict:** Too early and too expensive for a small club.

### 4. Payment Links (Mollie, Stripe, etc.)

Generate a payment link → member pays via card or iDEAL-like flow.
- Stripe: 1.5% + €0.25 per SEPA Direct Debit, or 2.9% for card
- Mollie: similar pricing
- Adds a proper payment flow with confirmation

**Verdict:** Overkill for annual dues. The 1.5-3% fee on a €180 membership = €2.70-5.40/member. Adds up for 80+ members.

### 5. Keep Signed URL QR + Improve UX (recommended)

The current signed-URL approach is fine. Improve it:
- Add a "Copy all details" button that copies formatted text to clipboard
- Add individual copy buttons for IBAN, BIC, amount, communication
- Display a QR that links to the verification page (already done)
- On the verification page, show a visual mock of what the bank transfer should look like
- Add a "Mark as paid" button (self-declaration) so the bureau knows to expect the transfer

## Decision

**Keep signed URL approach on main.** It's already anti-quishing safe and doesn't depend on any third-party payment system.

**Improvements to make (can be done on main):**
1. Better copy-to-clipboard UX on the payment verification page
2. Structured communication format for easier recognition
3. "I've paid" self-declaration button

**EPC QR code routes (`sepaPublic`, `sepa`) remain** but are not prominently offered in the UI. They still work for members whose banks accept them.

## Files Involved

- `app/Http/Controllers/QrCodeController.php` — all QR generation logic
- `resources/views/payment-verify.blade.php` — verification landing page
- `resources/views/cotisation.blade.php` — dues calculator with QR display
- `resources/views/profile/tabs/renewal.blade.php` — payment info in profile
- `resources/views/events/show.blade.php` — event payment QR
- `routes/web.php` — QR routes (lines 73-75, 235-237)
