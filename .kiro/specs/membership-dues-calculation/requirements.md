# Requirements — CEP Membership Dues Calculation & Bank-Transfer Preparation Screen

**Feature:** `/dues` — the screen on which a member of the Club Européen de Plongée
(CEP, affiliated to FFESSM) assembles a yearly membership order across four
option groups, sees the total computed live, and obtains the bank-transfer
details (IBAN / BIC / titulaire, read-only) plus a French *communication*
(mention) string summarising the order for the treasurer.

**Season under specification:** 2026.

**Mode:** This document is authoritative for acceptance. All user-facing labels
are French, verbatim as given in the source request. Currency is euro (€),
formatted with two decimals.

> **Status of this document.** Written against the existing DB-driven dues
> implementation on branch `adv_fee_prorata`
> (`FeeCalculationService`, `DuesCalculatorController`, `Season::taperPercentage`,
> `MembershipFeeComponent`, `dues-calculator.blade.php`). Where a requirement
> describes behaviour that the current code does **not** yet implement, the
> requirement is written as the *target* and the gap is flagged in
> §10 "Open Questions". No implementation is performed by this document.

---

## 1. Glossary (normative)

- **Cotisation** — club membership fee. Mandatory. One per member per season.
- **Licence FFESSM** — federation licence, includes RC (liability) insurance.
  Derived, never user-chosen.
- **Licence FLASSA** — separate federation component, new for season 2026. Flat
  10,00 € with age/status exemptions. Modelled as an instance of the
  membership-fee-component model, not a bespoke branch.
- **Assurance Individuelle (Loisir)** — optional personal dive-accident cover.
- **status_set** — the member's sticky base category (e.g. Fonctionnaire,
  Externe, Jeune). Persistent across seasons.
- **current-season status** — the member's status for the season under order,
  resolved from the sticky `status_set` at rollover; may differ from the base.
- **taper** — a seasonal, time-based discount defined in the database
  (`seasons.fee_taper_tiers`): effective anchor dates and a percentage per tier,
  plus new-season re-inflation to 100 %.
- **as_of_date** — the single date at which the taper factor is evaluated. It is
  `today` shifted by a bureau-configured grace offset (`dues_cutoff_grace_days`,
  default 0) so the cutoff falls a little later than today, leaving the bureau
  time to process. An absolute freeze (`fee_taper_reference_date`) overrides both.
- **prise de licence** — licence issuance; the age gate for cotisation
  eligibility and for the FLASSA age exemption is measured "à la prise de
  licence", not at current age.
- **communication / mention** — the French free-text reference string carried on
  the bank transfer.

---

## 2. The four option groups (normative structure)

Each group has radio semantics: exactly one option is active at any time.

### 2.1 Group 1 — COTISATION CEP 2026 (mandatory, user-chosen, exactly one)

| id                   | label (FR, verbatim)                                        | list price 2026 |
|----------------------|-------------------------------------------------------------|-----------------|
| `coti_fonctionnaire` | Cotisation au CEP fonctionnaire (18 ans et plus)            | 120 €           |
| `coti_externe`       | Cotisation au CEP externe (18 ans et plus)                  | 130 €           |
| `coti_jeune_16_17`   | Cotisation jeune (16-17 ans à la prise de licence)          | 55 €            |
| `coti_jeune_12_15`   | Cotisation jeune (12-15 ans à la prise de licence)          | 55 €            |
| `coti_enfant`        | Cotisation enfant (moins de 12 ans à la prise de licence)   | 55 €            |
| `coti_sympathisant`  | Cotisation sympathisant                                     | 30 €            |

*(List prices 120 € / 130 € pending confirmation — §10 G3/C3. The 105/110 in the
old screenshot and in `config/cotisation.php` are treated as superseded.)*

### 2.2 Group 2 — LICENCE FFESSM 2026 (mandatory, always derived, read-only)

Official 2026/2027 federation tariff and age bands (authoritative — supersedes
the old screen figures):

| id           | label (FR, verbatim)                    | price    | federation age band          |
|--------------|-----------------------------------------|----------|------------------------------|
| `lic_adulte` | Licence FFESSM adulte                   | 50,00 €  | à partir de 16 ans (16+)     |
| `lic_jeune`  | Licence FFESSM Jeune (12 à moins de 16) | 31,50 €  | de 12 à moins de 16 ans      |
| `lic_enfant` | Licence FFESSM Enfant                   | 14,50 €  | moins de 12 ans              |
| `lic_aucune` | Pas de licence (sympathisant)           | 0 €      | —                            |

### 2.3 Group 3 — ASSURANCE Individuelle (optional cover, exactly one, default `ass_aucune`)

Official 2026/2027 federation base tariff (Loisir, bénévole) — supersedes the old
screen figures:

| id               | label (FR, verbatim)     | price    |
|------------------|--------------------------|----------|
| `ass_loisir1`    | Assurance Loisir 1       | 25,00 €  |
| `ass_loisir1top` | Assurance Loisir 1 Top   | 48,00 €  |
| `ass_loisir2`    | Assurance Loisir 2       | 30,00 €  |
| `ass_loisir2top` | Assurance Loisir 2 Top   | 59,50 €  |
| `ass_loisir3`    | Assurance Loisir 3       | 51,00 €  |
| `ass_loisir3top` | Assurance Loisir 3 Top   | 99,00 €  |
| `ass_aucune`     | Pas d'Assurance Loisir   | 0 €      |

### 2.4 Group 4 — LICENCE FLASSA 2026 (mandatory-with-exemptions, derived, three-state)

Modelled as a `MembershipFeeComponent` instance with `taper_below_age = 18`,
`taper_ratio = 0` (free below the age), `age_anchor_date` = the shared prise-de-
licence anchor. Three states:

| state            | when                                       | effective price | in communication?   |
|------------------|--------------------------------------------|-----------------|---------------------|
| `required`       | licensed member, 18+ at prise de licence   | 10,00 €         | yes                 |
| `included_free`  | member < 18 at prise de licence            | 0,00 €          | yes (as "incluse")  |
| `not_applicable` | `coti_sympathisant`                        | — (absent)      | no                  |

---

## 3. Functional requirements — group semantics (EARS)

- **R-G1.1** The system SHALL present Group 1 (Cotisation CEP) as a mandatory
  single-select control offering exactly the options in §2.1.
- **R-G1.2** WHEN no cotisation option is selected, the system SHALL NOT compute
  a total and SHALL display an inline prompt to choose a cotisation.
- **R-G1.3** WHERE the member is classified (has a `status_set`), the system
  SHALL offer only the cotisation options that belong to that member's
  `status_set`.
- **R-G1.4** WHERE the member is unclassified (logged in, no `status_set`) or a
  guest, the system SHALL offer all self-selectable cotisation options.
- **R-G1.5** The system SHALL NOT offer the inactive/lifecycle status
  ("Ancien membre" / `former`) as a self-selectable cotisation to any viewer.

- **R-G2.1** The system SHALL render Group 2 (Licence FFESSM) as a read-only
  display of a single derived value, NOT as a disabled radio set.
- **R-G2.2** The system SHALL derive the Group 2 value purely from the selected
  Group 1 cotisation, per the mapping in R1 (§4).

- **R-G3.1** The system SHALL present Group 3 (Assurance) as an optional
  single-select control with exactly one option always active, defaulting to
  `ass_aucune`.
- **R-G3.2** WHEN the derived FFESSM licence is `lic_aucune`, the system SHALL
  force Group 3 to `ass_aucune` and disable the other assurance options.

- **R-G4.1** The system SHALL derive Group 4 (FLASSA) state from the selected
  cotisation and the member's age at the prise-de-licence anchor, producing
  exactly one of `required`, `included_free`, `not_applicable`.
- **R-G4.2** The system SHALL render Group 4 as read-only (derived), not as a
  user-selectable control.

---

## 4. Derivation, constraint & state rules (EARS)

- **R1 (licence derivation, always).** The system SHALL derive the FFESSM licence
  from the member's age at the shared prise-de-licence anchor `[G6]`, using the
  federation age bands, for every non-sympathisant:
  - age ≥ 16 → `lic_adulte` (covers `coti_fonctionnaire`, `coti_externe`,
    `coti_jeune_16_17`)
  - 12 ≤ age < 16 → `lic_jeune`
  - age < 12 → `lic_enfant`
  - `coti_sympathisant` → `lic_aucune` (regardless of age)

  The federation cutoff is strictly **under 16** for Jeune and **16+** for
  Adulte. The system SHALL use this single age-driven derivation so the club
  cotisation band label and the federation licence band cannot drift apart.

  **R1-note.** A 16- or 17-year-old (`coti_jeune_16_17`) therefore takes the
  *adulte* licence (50,00 €): FFESSM grants 16+ divers full underwater
  permissions (pending parental approval). FFESSM distinguishes only three bands
  — enfant, jeune, adulte. FLASSA remains `included_free` for them because they
  are still < 18 at the anchor. The system SHALL treat this pairing as correct
  and SHALL NOT charge FLASSA in this case.

  **R1-caveat (club vs federation band).** The club `coti_jeune_12_15` cotisation
  band MUST be interpreted as "12 to under-16" to line up with the federation
  Jeune band. Because R1 derives the licence from DOB (not from the cotisation
  label), a just-turned-16 member on `coti_jeune_12_15` correctly receives
  `lic_adulte`; the age-vs-status validation `[R-N3]` SHALL flag if a member's
  DOB and chosen cotisation band are inconsistent rather than silently
  mispricing the licence.

- **R2 (assurance requires a licence).** IF the derived FFESSM licence is
  `lic_aucune`, THEN the system SHALL force Group 3 to `ass_aucune` and disable
  the remaining assurance options.

- **R3 (applicability matrix by status).** The system SHALL apply component
  applicability as:
  - Cotisation: always required.
  - Licence FFESSM: required for non-sympathisant; `lic_aucune` for sympathisant.
  - FLASSA: `required` (18+) / `included_free` (<18) / `not_applicable`
    (sympathisant).
  - Assurance: optional for licensed members; forced `ass_aucune` for
    sympathisant.

- **R4' (season-S taper only).** WHEN loading `/dues` for season S, the system
  SHALL compute the effective cotisation price from the taper factor for season
  S evaluated at the as_of_date, and SHALL NOT display or bill a carried-over
  prior-season factor. A factor < 1.0 SHALL be legitimate only because a season-S
  taper anchor has genuinely passed.
- **R4'' (offered options from sticky set).** The system SHALL derive the offered
  cotisation options from the member's sticky `status_set`, NOT from the status
  held at the close of season S-1.
- **R4-note (no réduite tariff).** The system SHALL NOT expose a standalone
  "réduite" tariff and SHALL NOT carry an `is_reduite` flag. A reduced amount is
  only ever the product of `list_price × taper_factor`.

- **R5 (sympathisant → FLASSA not_applicable).** WHEN the cotisation is
  `coti_sympathisant`, the system SHALL treat FLASSA as `not_applicable`: the
  component SHALL NOT be an order line, SHALL NOT be a 0 € line, and SHALL NOT
  appear in the communication.

- **R6 (minor → FLASSA included_free).** WHEN the member is < 18 at the prise-de-
  licence anchor (and not sympathisant), the system SHALL treat FLASSA as
  `included_free`: effective price 0,00 €, but the component IS present and IS
  named in the communication as included.

- **R7 (adult → FLASSA required).** WHEN the member is licensed and 18+ at the
  prise-de-licence anchor, the system SHALL treat FLASSA as `required` at
  10,00 €.

- **R8 (two distinct zero states).** The system SHALL distinguish
  `effective_price == 0` (a real, present, free component) from
  `applicable == false` (no component). These two states SHALL drive two
  distinct downstream behaviours (see R5 vs R6) and SHALL NOT be collapsed into
  "price 0".

---

## 5. Taper requirements (EARS)

- **R-T1.** The system SHALL compute the effective cotisation price as
  `list_price × taper_factor(status, season, as_of_date)`, where `taper_factor`
  is read from the authoritative DB taper definition
  (`seasons.fee_taper_tiers`) and `as_of_date` is `today + dues_cutoff_grace_days`
  (settings, default 0), overridden by `fee_taper_reference_date` when pinned.
  The system SHALL NOT embed taper dates, percentages, or the grace offset in
  screen code or config.
- **R-T2.** The system SHALL treat `taper_factor` as time-based: full price early
  in the season, reduced after the defined taper anchor(s), per stored tiers.
- **R-T3.** At rollover the system SHALL return the factor to 100 %; a new season
  SHALL simply start at factor 1.0 (same mechanism as R-T2, not a separate rule).
- **R-T4 (taper token wording).** WHERE a taper tier applies (factor < 1.0), the
  system SHALL take the reduction wording used in the communication from the DB
  taper definition and SHALL NOT invent the literal "Réduite". *(Exact wording
  and whether a tier name is stored — pending, §10 G2/C2.)*

---

## 6. Computation (EARS)

- **R-C1.** The system SHALL compute the total as:
  `Total = (list_price(cotisation) × taper_factor)`
  `      + price(licence FFESSM)`      *(derived, R1)*
  `      + effective_price(FLASSA)`    *(10 / 0 / omitted, R5–R7)*
  `      + price(assurance)`.          *(R2, default aucune)*
- **R-C2.** The system SHALL recompute the total on ANY selection change AND
  whenever the age/status inputs or the as_of_date change, because the FFESSM
  licence, FLASSA state, and taper factor are all derived.
- **R-C3.** The system SHALL display all monetary amounts in euro with exactly
  two decimals.

---

## 7. Communication string (EARS) — primary deliverable

- **R-M1.** The system SHALL produce a stable, treasurer-parseable machine
  communication of the form
  `{club_short_code}-{season}-{member_id}-{NOM PRENOM}{+optional_slugs}`
  (per decision G7). The human French sentence
  `"{Prénom} {NOM} Cotisation {saison} [<taper token> ]{statut court} {licence FFESSM courte} [FLASSA|FLASSA incluse] {assurance courte}"`
  is retained below as a human-readable illustration of the composed order and is
  NOT a delivery requirement.
- **R-M2.** The system SHALL emit the taper token ONLY for a genuine in-season
  reduction (factor < 1.0), never for a full-price order, and SHALL use the DB
  tier wording (R-T4), never the literal "Réduite".
- **R-M3.** The system SHALL emit the FLASSA token as "FLASSA" when `required`,
  as "FLASSA incluse" when `included_free`, and SHALL omit it entirely when
  `not_applicable`. *(Exact FLASSA short label / "incluse" wording pending,
  §10 G4.)*
- **R-M4.** The system SHALL place the FLASSA token in the communication at the
  position confirmed in §10 G5 (relative to the assurance token).
- **R-M5.** The member name in the communication SHALL be rendered as
  `{Prénom} {NOM}` with the surname uppercased.

**Worked examples (at 100 % taper).** These are acceptance fixtures; adjust only
if a taper tier applies:

1. Fonctionnaire, 18+, Loisir 1 Top:
   `120 + 50,00 + 10 + 48,00 = 228,00`
   `"Eddy COLLART Cotisation 2026 Fonctionnaire Loisir 1 Top FLASSA"`
2. Jeune 12-15, Loisir 1:
   `55 + 31,50 + 0 + 25,00 = 111,50`
   `"… Cotisation 2026 Jeune Licence Jeune FLASSA incluse Loisir 1"`
3. Jeune 16-17, no assurance:
   `55 + 50,00 + 0 = 105,00` (adult FFESSM licence, FLASSA included_free)
   `"… Cotisation 2026 Jeune Licence adulte FLASSA incluse"`
4. Sympathisant, no licence, no assurance:
   `30,00` (no FFESSM, no FLASSA token, no assurance)
   `"… Cotisation 2026 Sympathisant"`

> Note: per decision G7, the delivered communication is the machine string of
> R-M1 (`{club_short_code}-{year}-{id}-{NOM PRENOM}{+opts}`). The human sentence
> above is illustrative of the order's contents, not the delivered format.

---

## 8. Payment summary (EARS) — read-only

- **R-P1.** The system SHALL display, read-only: IBAN, Titulaire (beneficiary),
  Banque, Code BIC, Montant (= Total), Mention (= generated communication).
- **R-P2.** The system SHALL source the fixed club details (IBAN, Titulaire,
  Banque, BIC) from stored settings (`ThemeSetting`), never from screen code.
- **R-P3.** WHEN the club IBAN is not configured, the system SHALL display a
  clear administrator-facing notice instead of an incomplete transfer block.

---

## 9. Separation of concerns & non-functional (EARS)

- **R-S1 (domain).** The four tables, R1–R8, R4'/R4'', R-T1–R-T4 and the total
  SHALL be implemented as pure, testable domain logic with no UI dependency.
- **R-S2 (presentation).** The radio groups, read-only derived licence, forced/
  disabled states (R2), live total, and live communication SHALL be the
  presentation layer.
- **R-S3 (payment summary).** The read-only club block plus the two computed
  fields (Montant, Mention) SHALL be a distinct concern.
- **R-S4 (data, not code).** Prices, labels, mappings, taper, and FLASSA
  parameters SHALL live in data/config/DB. A new season SHALL be a data change,
  not a code change.
- **R-N1 (French UI).** All user-facing labels SHALL be French, verbatim as in
  §2, wrapped for translation.
- **R-N2 (accessibility).** Radio groups SHALL use fieldset/legend, be
  keyboard-operable, and expose ARIA. Forced/disabled (R2) and read-only-derived
  (Groups 2 and 4) states SHALL be ANNOUNCED to assistive technology, not merely
  greyed visually.
- **R-N3 (validation).** The system SHALL validate age-vs-status eligibility "à
  la prise de licence" and surface a clear inline error on mismatch.

---

## 10. Resolved decisions (confirmed by the bureau)

The open questions raised after the first draft were resolved as follows. These
decisions are now normative and are carried into `design.md`.

- **G1 / C1 — as_of_date = today + configurable grace.** The taper `as_of_date`
  is the date the member is navigating the screen, shifted later by a bureau-
  configured grace offset held in app settings (`dues_cutoff_grace_days`, default
  0 days). This keeps the cutoff a little later than "today" so the bureau has
  time to process, without a per-request payment-date or licence-date input. An
  absolute freeze (`fee_taper_reference_date` ThemeSetting) overrides the
  computed date for administrative pinning.
- **G2 / C2 — taper token stays anonymous / "Réduite".** The taper tier may
  remain anonymous for now; where a token is shown, the literal `Réduite` is
  acceptable. No per-tier wording field is required at this stage.
- **G3 / C3 — this is the 2027 season; use latest DB amounts.** The order is for
  the **2027** dues season (`Season::currentDuesYear()` today = "2027"). Amounts
  come from the latest values held per component in the DB — in this case
  `coti_fonctionnaire = 120 €` and `coti_externe = 130 €`. The prompt's own
  figures are advisory; the DB is authoritative.
- **G4 — FLASSA labels.** FLASSA is a licence; its short label follows the FFESSM
  licence labelling convention. Free-under-18 case is surfaced as "incluse".
- **G5 — FLASSA slot = FFESSM licence block.** FLASSA is a licence and belongs in
  the SAME block as the FFESSM licence (Group 2), not adjacent to the assurance
  token.
- **G6 — shared age anchor: yes.** The FLASSA < 18 boundary and the cotisation
  "à la prise de licence" gate use the SAME `age_anchor_date`.
- **G7 — machine communication string is OK.** Keep the current machine-style
  communication (`{club_short_code}-{year}-{id}-{NOM PRENOM}{+opts}`). The human
  French sentence of §7 is descriptive only; it is NOT a delivery requirement.
  R-M1 is therefore relaxed to "the communication SHALL be a stable, treasurer-
  parseable machine string"; §7's sentence and worked examples remain as
  human-readable illustrations of the composed order.
- **G8 — seed as 2027 values.** Seed for the 2027 season: the six cotisations
  (as `MembershipFee` rows keyed by status), the FFESSM licence components, the
  FLASSA licence component, and the seven assurance options (as
  `MembershipFeeComponent` rows).
- **G9 — licences derived, mandatory except sympathisant.** Licences are
  mandatory for members except sympathisant. From the member's status and age we
  determine which licence(s) are applicable; this becomes a domain rule (R1/R3)
  in `FeeCalculationService`.
- **G10 — stack confirmed.** Laravel 12 / PHP 8.3 / Blade + Bootstrap,
  server-rendered with a small progressive-enhancement JS layer for live
  recompute, matching the existing `dues-calculator.blade.php`. No SPA.

---

*End of requirements.md. Decisions resolved; `design.md` follows.*
