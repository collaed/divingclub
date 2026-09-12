# Membership lifecycle — questions for the bureau

We can't build the automatic membership / licence lifecycle until these nine
points are decided. Each one is a real choice the club makes, not a technical
detail. For every question there's **why it matters**, **what your answer
changes**, and **our suggestion** — reply with "suggestion is fine" or your own
answer.

Design context: `docs/MEMBERSHIP-LIFECYCLE.md`. Already settled (2026‑09‑09):
suspended = hard block after a short grace; licence coverage = at least one
valid licence; youth is age‑banded not a tier; FFESSM licence validity =
`min(cert_date + 1 year − 1 day, 31 Dec of the target year)`.

---

## 1. Newsletters during the warning window

When a member stops paying, on 1 January they become **lapsed** and see a
warning banner for a short period before access is blocked.

- **Why it matters:** decides whether a lapsed member keeps getting club
  newsletters and event mail during that window, or only renewal reminders.
- **Your answer changes:** the mail-eligibility filter (one rule).
- **Our suggestion:** during the warning window they still get everything
  (they're a hair away from renewing — keep them in the loop); once blocked,
  renewal reminders only.

## 2. Length of the warning window

- **Why it matters:** how long a non-paying member keeps site access after
  1 January.
- **Your answer changes:** one setting.
- **Options:** (a) a fixed number of days (we suggest **45–60**), or (b) a
  fixed calendar date every year ("access blocked from 1 March").
- **Our suggestion:** fixed date **1 March** — predictable, easy to
  communicate, lines up with the AGA season.

## 3. Retired external members

An external adult (`membre externe`) who retires — do they move to the
cheaper **membre assimilé** rate?

- **Why it matters:** it's a real fee difference.
- **Your answer changes:** whether the tier auto-suggests a change, and who
  confirms it.
- **Our suggestion:** it does **not** happen automatically. The member asks,
  the bureau flips the tier for the next season. (If you'd rather it be
  automatic from a "retired" checkbox on the profile, say so.)

## 4. External instructors → membre de droit

External instructors get the *membre de droit* rate.

- **Why it matters:** currently that would be a manual tier assignment every
  year.
- **Your answer changes:** whether holding the `instructor` role automatically
  sets the tier to *membre de droit*, or the bureau sets it by hand.
- **Our suggestion:** automatic from the instructor role — one less thing to
  remember. The bureau can still override.

## 5. When is a season "paid"?

Fees can be paid in instalments, and mid-year joiners pay a reduced (tapered)
amount.

- **Why it matters:** decides the exact moment a member flips from "fee due"
  to "covered".
- **Options:** covered when (a) the full annual amount is in, (b) the tapered
  amount they actually owe is in, or (c) any payment has been reconciled
  against their fee.
- **Our suggestion:** (b) — the tapered amount they owe. Partial payments show
  as "partially paid" and don't yet grant coverage.

## 6. Validity formula for FLASSA (and any other federation)

We have FFESSM's licence-validity rule. We need the same for **FLASSA** and any
other federation the club orders licences from.

- **Why it matters:** the app computes each licence's `valid_from` /
  `valid_until` from the member's age, the medical-certificate date and the
  federation's rule. Without the rule it can't.
- **Your answer changes:** one formula per federation.
- **What we need:** the Excel-style expression, with `cert_date`, `date_of_birth`
  and `target_year` as the inputs — e.g. *"valid to 31 December of the target
  year, provided the medical certificate is dated within the previous 12
  months; free and no licence below age 18."*

## 7. Voting age for *membre associé*

The tier table says *membre associé* (child/spouse of a member) votes at the
assembly "adults only".

- **Why it matters:** decides who appears on the voting roll.
- **Your answer changes:** one age check.
- **Our suggestion:** 18 or older on the season anchor date. Minors of any tier
  never vote — confirm that's right.

## 8. Honorary members

*Honoraire* members pay no fee but keep full access and licences.

- **Why it matters:** the model records one row per member per season. An
  honorary member has no payment to reconcile.
- **Options:** (a) the system auto-creates a "waived / covered" season row for
  each honorary member every year, or (b) honorary members sit outside the
  per-season model and are simply always treated as covered.
- **Our suggestion:** (a) — a waived row each year keeps the history uniform
  and the member counts honest.

## 9. Partner-club / external divers

People who dive with the club on a one-off basis via a partner club
(`external_registrations`) — not members.

- **Why it matters:** decides whether this lifecycle model covers them at all.
- **Our suggestion:** out of scope here — they're a separate, lighter track
  (a registration per outing, no season membership). Confirm.

---

## What happens once these are answered

The build is small and low-risk: a couple of database migrations, one status
enum, an allowed-transitions map, and guard checks on the transitions. No new
libraries, no rewrite of existing records — every season and every licence
stays its own row and the member's history is derived from them.
