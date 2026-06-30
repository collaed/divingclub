# Payment QR — The Trust Problem

## Status: Parked (branch `feature/payment-qr-alternatives`)

## The Real Issue

The problem isn't QR format or content — it's **trust infrastructure**. Banks are moving toward a model where payment-initiating QR codes must come from **domains/issuers the bank has a pre-established relationship with**.

The reasoning:
- Banks need to know the QR issuer keeps logs and can be held accountable
- Transactions initiated from "trusted" QR sources can be reversed without the bank bearing liability
- A small club's domain (even with HTTPS + HMAC) won't be on any bank's allowlist without a commercial contract

### Timeline

1. **Now (2026):** EPC QR codes still work in most banking apps, but some banks (ING, KBC) have started ignoring/warning on them
2. **Near future:** Banking apps will stop auto-filling payment details from arbitrary QR scans
3. **Eventually:** Only QRs from registered PSPs (Wero, Payconiq, bank-certified portals) will trigger the "pre-fill transfer" flow

### What this means for a small club

No matter how cleverly we sign or format our QR, **banking apps won't trust it** without a commercial relationship between the club (or its PSP) and the bank. The options are:

| Option | Cost | Friction | Future-proof |
|--------|------|----------|--------------|
| Raw EPC QR | Free | Low (while it works) | ❌ Dying |
| Signed URL QR → info page | Free | Medium (manual copy) | ✅ Always works |
| Wero / R2P via PSP | €10-30/month + per-tx | Low | ✅ But expensive |
| Stripe/Mollie payment link | 1.5-3% per tx | Low | ✅ But expensive |

## Current Implementation

We already have the best free option: **signed URL QR → verified payment info page**.

```
Member scans QR → opens https://club.lu/pay/verify?a=180&c=CEP-2026-SMITH&s=HMAC
    → sees IBAN, BIC, amount, communication
    → copies details into banking app manually
```

The QR is just a convenient way to get to the payment instruction page — it doesn't (and can't) trigger a bank transfer directly.

## What We Keep

- **Signed URL QR** — the QR encodes a URL, not bank details. Safe from quishing.
- **Verification page** — shows payment details with copy buttons, TLS proves club identity.
- **Legacy EPC routes** — kept but hidden. Still work for the (shrinking) set of banking apps that accept them.

## What We Could Improve (low effort, on main)

1. **Copy-to-clipboard UX** — individual buttons for IBAN, amount, communication on the verification page
2. **"I've paid" button** — member self-declares, bureau sees it as a hint during reconciliation
3. **Structured communication** — use `+++123/4567/89012+++` format (recognized by some Belgian/Lux banking apps)
4. **Print-friendly layout** — some members will want to type details from a printout

## What We Won't Do (unless the economics change)

- Wero integration (requires PSP contract)
- Stripe/Mollie (3% on €180 = €5.40/member, €430/year for 80 members)
- Bank-specific deep links (no published APIs in Luxembourg)
- Request-to-Pay (not supported by consumer banks in Lux yet)

## Re-evaluate When

- Wero launches a "non-profit / association" tier with flat pricing
- A Luxembourg PSP offers R2P for < €5/month
- A standardized "payment request" deep link emerges across banking apps

## Files Involved

- `app/Http/Controllers/QrCodeController.php` — all QR generation
- `resources/views/payment-verify.blade.php` — verification landing page
- `resources/views/cotisation.blade.php` — dues calculator with QR
- `resources/views/profile/tabs/renewal.blade.php` — payment info in profile
- `routes/web.php` — QR routes (lines 73-75, 235-237)
