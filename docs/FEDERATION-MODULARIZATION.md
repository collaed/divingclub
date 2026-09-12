# Modularizing federations — analysis

Status: **analysis, not a plan.** Nothing here is decided or built. It maps
where FFESSM/FLASSA are actually wired into the app today, names the real
coupling points with file references, and lays out the options for making a
federation an activatable, isolated unit — so another club could run just one
federation, or a different pair, or three, without editing this app's core
files. §7 lists the decisions that need an answer before this becomes a plan.

---

## 1. The ask, precisely

Today CEP runs exactly two federations side by side (FFESSM + FLASSA), and
every place that needs to know *which* federation does something checks the
acronym by string. The goal: a club activates federation A, B, and/or C from
data (or config), each federation's certifications/validity rules/card
rendering/document parsing are self-contained, and the system already
supports 2–3 running side by side — which, at the data-model level, it does.
What doesn't yet exist is a *seam* — an interface a new federation's rules can
be written against without touching the ~6 places that currently spell out
"FFESSM" or "FLASSA" by name.

## 2. What's already federation-agnostic (safe as-is)

These are genuinely data-driven today — a new federation needs only a new
row, no code:

| Piece | Table / class | Why it's already fine |
|---|---|---|
| Federation identity | `federations` (`acronym`, `full_name`, `visibility`) | Plain CRUD, `Admin\FederationController` — any bureau can add/rename/hide one today |
| Certification levels | `certification_levels` (`federation_id` FK) | Scoped per federation, no hardcoded names |
| Medical compliance *thresholds* | `medical_compliance_rules` (`federation_id`, `age_bracket_*`, `cert_type`, `validity_months`) | The **data** is generic — see §3 for why the **engine** reading it isn't |
| A member holding N licences | `member_licences` (`federation_id` FK, one row per federation) | Already 1:many; dashboard, medical review, and the profile card loop over multiple licences without assuming a count of 2 |
| Verification-link fallback | `QrCodeController::federation()` | Already has a generic branch for "any other federation" — the one place that got this right from day one (see §3) |

The multiplicity itself — "2 or 3 federations side by side" — is not the
gap. The gap is that *behavior* for a given federation is decided by an
`if ($acronym === 'FLASSA')` scattered across the codebase instead of being
attached to the federation itself.

## 3. Where FFESSM/FLASSA are actually hardcoded

Six real coupling points, each a different *kind* of federation-specific
behavior:

### 3a. Membership-fee eligibility — the deep one

`app/Services/LicenceResolver.php` + `app/Services/FlassaState.php` are a
small, pure, well-tested domain engine — but it hardcodes exactly these two
federations as PHP constants: FFESSM's three age-band slugs
(`FFESSM_ADULTE`/`JEUNE`/`ENFANT` at 12/16), and FLASSA's specific rule
("included free under 18, required at 18+", modeled as a 3-state enum
`FlassaState`). `MembershipFeeComponent::KIND_FFESSM_LICENCE` /
`KIND_FLASSA` name them again as the fee-line "kind" enum.
`FeeCalculationService` calls `LicenceResolver` directly and reads those two
kinds by name in half a dozen places (`app/Services/FeeCalculationService.php`
lines ~57, 96–128, 288–303).

This isn't a lookup-table problem — FFESSM's rule (age → one of 3 discrete
tiers) and FLASSA's rule (age → free-or-full) aren't the same *shape* of
rule. A third federation could need a third shape entirely (e.g. a flat fee
regardless of age, or a family discount). There is currently no data
structure that could express "a federation's fee-eligibility rule" generically
— this is a real design question, not just a refactor (see §6, §7.1).

### 3b. Medical certificate validity formula

`app/Services/MedicalComplianceService.php` (~lines 97–119): a two-branch
`if ($fed->acronym === 'FLASSA')` computes a calendar-anchored expiry
(FLASSA), `else` a rolling day-count from `validity_months` (FFESSM). The
`medical_compliance_rules` *table* is federation-scoped and generic; the
*formula type* that interprets those rows is not — it's exactly the "FLASSA
valid_until formula: tbd" gap already flagged as open question #6 in
`docs/MEMBERSHIP-LIFECYCLE.md`.

### 3c. Document ingestion (licence-card scanning)

`app/Helpers/FlassaLicenceTextParser.php` + the FLASSA-only gate in
`app/Jobs/ProcessLicenceScan.php` (`extractFromText()`,
`extractCardIssuedAt()`) and `app/Http/Controllers/Admin/LicenceScanController.php`.
This one is actually **well-isolated already** — the FLASSA-specific parsing
is one helper class behind one `$federationAcronym !== 'FLASSA'` guard per
call site, not spread through business logic. It needs a registry
(acronym → parser class) instead of a string comparison, which is a
mechanical change, not a design one.

### 3d. Card rendering

`resources/views/profile/tabs/renewal.blade.php` dispatches to
`profile/partials/flassa-card.blade.php` or `.../ffessm-card.blade.php` via
`@if($lic->federation->acronym === 'FLASSA') ... @elseif(... 'FFESSM') ...`.
A third federation needs a third `@elseif` and a third hand-built partial —
mechanical, low-risk to generalize (see §6, step 4).

### 3e. Verification links

`app/Http/Controllers/QrCodeController::federation()` — already has the
right instinct: a generic SHA-256 fallback URL for "any other federation",
with FFESSM's InfoLicencié deep link as the one named special case. The
partials (`ffessm-card.blade.php`) duplicate that FFESSM URL-building logic
inline rather than calling the controller's, which is a smaller, separate
cleanup.

### 3f. New: card issuance date (yesterday's feature)

`app/Helpers/PdfMetadata.php` + its call sites — **deliberately** scoped to
FLASSA only, because the "PDF CreationDate = issuance date" heuristic was
verified against FLASSA's specific print pipeline (37 real cards) and hasn't
been checked for any other federation's PDF generator. This is a case where
scoping to one federation was the *correct* call, not a shortcut — worth
naming so it isn't "fixed" into a false generalization later.

## 4. Two different problems, two different fixes

- **3b, 3c, 3d, 3e** are "behavior keyed on a string in several places."
  Fixable by giving each federation a small class that implements a shared
  interface, and replacing each `if (acronym === X)` with a lookup — no new
  concepts, just moving code that already exists into the right seam. Low
  risk, mechanical, testable step by step.
- **3a** is "the domain doesn't have a shape for this yet." FFESSM and
  FLASSA's fee rules aren't expressible as data of the same shape today, and
  it isn't obvious a third federation's rule would fit either shape. This
  needs a design decision (§7.1) before it can be "modularized" rather than
  just moved.

## 5. Proposed seam (for review, not decided)

A `FederationRuleProvider` interface, one implementation per federation,
resolved by acronym from a small registry — **no new plugin-loading
mechanism, no package boundary** — just an interface inside the existing
`app/Services/` layer (deptrac already allows `Services → Services`):

```php
interface FederationRuleProvider
{
    public function medicalCertValidity(Document $cert, ?Carbon $dateOfBirth): ?Carbon;
    public function feeEligibility(string $cotisationSlug, ?int $ageAtAnchor): FederationFeeOutcome; // shape TBD, see §7.1
    public function parseLicenceDocument(string $text): ?array;   // null = "not this federation's format"
    public function verificationUrl(MemberLicence $licence): ?string; // null = use the generic QR fallback
    public function cardComponent(): ?string;                      // a Blade component name, null = generic fallback card
}
```

```php
// config/federations.php
return [
    'FFESSM' => \App\Services\Federations\FfessmRuleProvider::class,
    'FLASSA' => \App\Services\Federations\FlassaRuleProvider::class,
];
```

A federation with no matching provider gets sensible generic defaults
(matching `QrCodeController`'s existing fallback instinct) rather than an
error — so adding a federation row in the admin UI never breaks anything
even before anyone writes its provider.

This stays a single Composer-managed app — not truly loadable third-party
plugins (see §7.3 for when that would actually matter).

## 6. Migration path (each step shippable and behavior-identical until the last)

1. Introduce the interface + registry; wrap the *existing* FFESSM/FLASSA code
   behind `FfessmRuleProvider`/`FlassaRuleProvider` with **zero behavior
   change** — existing tests must pass unmodified. Proves the seam before
   anything moves.
2. Move `MedicalComplianceService`'s calendar-vs-day-count branch (3b) behind
   the provider.
3. Move card dispatch (3d) — `renewal.blade.php` loops federations calling
   `cardComponent()` instead of `@if`/`@elseif`.
4. Move `QrCodeController` + `FlassaLicenceTextParser` dispatch (3c, 3e)
   behind the provider.
5. **Last, and only with an answer to §7.1**: move `LicenceResolver`/
   `FeeCalculationService` (3a) behind `feeEligibility()`.
6. Only once all of the above ships: pick a real third federation to
   implement, as the actual test that the seam holds for something that
   isn't FFESSM or FLASSA.

## 7. Open questions

1. **Is a generic fee-eligibility shape even possible?** FFESSM's 3-tier
   age bands and FLASSA's free-under-18 binary aren't the same shape. Options:
   (a) design a rule DSL expressive enough for both plus a plausible third
   (age brackets → amount-or-percentage, roughly what `membership_fees` /
   `MembershipFeeComponent`'s taper fields already do for the *base*
   cotisation) — real design work; or (b) accept that fee eligibility is
   *always* bespoke code per federation, and "modular" here just means "one
   isolated `FederationFeeProvider` class per federation" rather than a
   declarative rule someone fills in from the admin UI. (b) is much less
   work and may be the honest answer.
2. **Per-club or per-season activation?** Is a federation's activation a
   one-time install-time choice (today's `visibility` column is enough), or
   should a club be able to add/drop a federation mid-life with history
   (a member's old FLASSA licences staying visible after the club stops
   offering it)? Affects whether `federations.visibility` needs a real
   activation log.
3. **Package boundary or in-app seam?** Is "plugin" meant literally — a
   separately installable/licensable unit, e.g. because a federation
   integration might one day be sold or maintained separately from core —
   or is "modular" purely about not re-touching the same 6 files for every
   new federation? The interface in §5 solves the second; the first is a
   materially bigger change (Composer packages, a plugin-discovery
   mechanism, versioning across the boundary) that doesn't seem justified
   unless there's a concrete reason to expect federation code to live
   outside this repo.
4. **Who writes a new federation's provider?** If it's always this repo's
   maintainer, §5's design is sufficient. If clubs or community members
   should be able to add one without a PR to core, that pushes toward §7.3's
   heavier answer.
5. **FLASSA's calendar validity rule and other federations' formulas** are
   still genuinely undocumented (membership-lifecycle open question #6) —
   modularizing the *engine* doesn't remove the need to actually get each
   federation's real formula from the bureau.
