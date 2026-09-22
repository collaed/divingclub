# Changelog

What reached production, newest first. Conventions are in
`.kiro/steering/release-notes.md`. Entries before 2026-09-16 were not recorded.

## 2026-09-22 — prod

_Data-only change already applied on production (2026-09-21): 21 members' IBAN filled from the 2026 bank export (payees of the club's own reimbursements); 2 already held the same value. Nothing was overwritten._

_The membership-renewals-and-earlier block below reached production earlier today as a side
effect of deploying the kanban board directly from its feature branch (which was based on the
then-current `main`) rather than via the usual `main` → prod path — worth knowing since it
wasn't announced as its own release at the time. Confirmed working (health check, a real page
load) before building further on top of it._

### New
- **Ledger** (Admin → Finance → Ledger, bureau_master only): import the bank's own `.xlsx`
  account-history export (chosen over its CSV export, whose character encoding is inconsistent
  between exports — the `.xlsx` carries real dates, amounts and accented text with no guessing).
  Each line is checked against the running balance within its statement, deduplicated against
  earlier imports, and classified: deep green when it matches a known amount, light green when
  the counterparty and purpose are known but there's nothing to check against, amber to confirm,
  red when nothing is recognised. Fixed tags (from the club's own accounting categories) and
  variable "fill once, apply to several lines" tags (`#for: ___`, `#covers: ___`…) can be
  applied singly or in bulk; a name-only "Group? Cap Vert…" suggestion turns into a real
  operation (a trip, a closed loop, a recurring cost centre) in one click, never automatically.
  A statement balance-check table sits at the bottom, per source file.
- **Actions from compte-rendus** (Admin → Content & comms, bureau_master only): a kanban board
  (To do / In progress / Done) of action items extracted from the club's meeting minutes. A
  background task on production (the documents live there) picks one unprocessed compte-rendu
  every 3 hours, extracts its text and asks an AI model for the actions and who's responsible,
  and posts the result onto the board. A card links back to its source document and date, and
  can be moved between columns or discarded as obsolete without being deleted.
- Members Directory (`/members`): a **Level** filter, matching either the scuba
  (`certification_level`) or the apnea (`apnea_level`) field — a level like "N1" can live in
  either depending on how the member's data was entered.
- **Membership renewals screen** (Payments → Membership renewals): every current member who
  has not paid the season, with the amount to expect — their commitment if they made one,
  otherwise their status and last season's insurance at this season's prices. A green
  "Received" button confirms that amount; typing another amount tells you which insurance
  option it corresponds to (or refuses it and lists what the member could owe). A paid
  membership marks the season as paid on the member, including when it is confirmed from
  bank reconciliation. "Paid another way" on each row records that a member paid whatever
  the amount or channel (cash, an unreconciled transfer, other), with an optional note.
- **Insurance to register** (Payments → Membership renewals → Insurance to register): every
  paid membership that carries an insurance option, in the order it was paid, with per-option
  totals and a tick to record that it has been registered with the insurer. Next season's
  proposal starts from the insurance paid the season before.
- Honoraire members are marked as paid for the season automatically from 1 October.
- **Phone numbers** (profile, emergency contact, registration, new member): a country-code
  selector ordered by club usage (Luxembourg, France, Belgium, Germany, Finland…) and a
  formatted number field. Typing "00" or "+" picks the country from the code.
- **Honoraire: "No licence" option** on the dues calculator, only shown to Honoraire
  members. Ticked, they pay nothing (no FFESSM licence, FLASSA or insurance); unticked
  they are charged the licence they need.
- Members roster: **"Actif" is now a filter** listing every member in good standing this
  season (paid the current season, or honoraire), regardless of status.

### Changed
- Sympathisant pays the nominal amount whatever the age (no under-18 reduction).
- **Ages are now measured on 1 November** (the start of licence ordering) instead of
  1 September: FFESSM licence band, FLASSA and the under-18 share of the cotisation.
  Someone turning 12 in September or October is now priced as 12.
- "Actif" is no longer offered when creating a member or editing a profile (a member who
  already has it keeps it displayed).

- Members Directory (`/members`): the age filter is now five single-condition options
  (< 12, < 14, < 16, < 18, 18 and over — matching the ages the club's own course levels and
  badges are gated at) instead of ten-year brackets.

### Data changes
- Payments gain a payment method, a note and an "insurance registered" date (new columns, empty for existing rows).
- The FLASSA licence age date moved from 1 September to 1 November (one row).
- Two new tables for the ledger (`ledger_transactions`, `ledger_counterparties`, `ledger_tags`,
  `ledger_operations`) and one for the kanban board (`kanban_cards`); both start empty on
  production. The ledger's fixed tags and the club's known recurring non-member counterparties
  (Steinfort, the insurer, the two FFESSM federation senders, Luxair…) are seeded.

### Fixed
- Background jobs that run longer than a minute (article translation) were killed
  whenever the queue scaled its workers down, then reported as failed an hour later.
  Workers now get up to 30 minutes to finish; this cleared the "50 failed jobs"
  health warning on staging.
- The weekly backup's health signal no longer reports success when the backup itself
  failed; a failure now shows on the dashboard and the external monitor.
- A page-load toast for a "your changes were saved"-style message silently failed everywhere
  (the function it called was defined lower in the page than where it was first used) — it
  still showed as a plain banner, so this was invisible unless you had the console open.
- The ledger's statement balance-check table merged two different files' identically-numbered
  statements (e.g. both exports call their first statement "1") into one row.

### Needs attention
- The ledger and the kanban board are restricted to `bureau_master` and marked in red in the
  nav, like Votes — the same convention as the club's other most sensitive admin screens.

## 2026-09-20 — prod at `7476988` (second release, from main)

### New
- Events calendar: the equipment-priority hatch (kids / PN1 / PN2) now shows on the
  Events month view too, with a legend, and clicking a greyed-out day from the
  previous or next month jumps the calendar to that month.
- Automation emails can use placeholders in the subject and body: `{event}`, `{date}`,
  `{time}`, `{datetime}` and `{location}`.

### Fixed
- Once a rule cancels an event, the event's other automation rules no longer send emails.
- An "N hours before" rule found more than 30 minutes late (event moved later, scheduler
  down) is recorded without sending, instead of a stale "2h left" mail.
- Rescheduling an event now makes its automation rules due again at the new time.
  Before, a rule that had already fired (or a registration-close rule after the
  event was moved) silently never fired again.

### Changed
- Automation rules: a registration-close rule on an event that has already started
  is now recorded without running (nothing left to act on).

### Data changes
- The old `automation_evaluated_at` flag on events is replaced by per-rule fire
  records; existing records were converted automatically.

### Needs attention
- A rule that already fired for an event's current schedule stays fired; to re-test
  one, change the event's close or start time.

## 2026-09-20 — prod at `3fca41c` (release/dues-2026-09-20)

A partial release: only the membership-dues work was promoted; the events and
automation changes stayed on staging until the release above.

### Changed
- **Members under 18 pay 50% of the club cotisation.** It was missing: a 12-year-old
  Associé was calculated at 120 + licence instead of 60 + licence. Applies to every
  status (Membre de droit and its sub-cases, Externe); odd amounts round up to the euro.
  The FFESSM licence keeps its own age bands (under 12: 14.50, 12 to under 16: 31.50,
  16 and over: adult) and FLASSA is charged from 18. The age and percentage are set per
  season on the season page ("Under-Age Club Fee"; defaults 18 / 50%). Applies to dues
  calculated from now on; existing dues are not recalculated.
- **Junior and Enfant are removed as statuses**, with the "Jeune" set. A child is a
  Membre de droit (Fonctionnaire, Associé, Assimilé, Famille) or an Externe member; age
  only drives the under-18 share and the licence band. No member held either status.
- Fonctionnaire is now a sub-case of Membre de droit for the cotisation, like Associé,
  Famille and Assimilé (a fee set directly on it still wins). Externe keeps its own rate.
- **"Actif" is no longer offered on the dues calculator** (and can't be committed): it is
  a system status given to new and imported members. The Externe set's default is now
  Externe.

### Fixed
- The dues calculator no longer refuses Externe or Fonctionnaire for a member under 18
  ("The selected membership does not match the member age...") and now states the
  under-18 rule up front; the result shows an "Under 18" line.
- Membre de droit, Associé, Famille and Assimilé calculated 0 for 2027 (no 2027 fee row
  for Membre de droit); added at 120 ("Bureau du 03/09/26").

### Data changes
- Deleted the Junior and Enfant statuses, their 2027 fee rows and the Jeune set (2
  statuses, 2 fee rows). One member parked in the Jeune set (a former member) is now
  unclassified. No member lost a status: counts per status are identical before and after.
- Added the 2027 Membre de droit fee (120) and two season columns for the under-age rule.

## 2026-09-18 — prod at `cbc75b5`

### New
- **Document Intake** (admin): drop licence scans and bank statements on one screen;
  the type is detected automatically and can be corrected per document.
- **Bank reconciliation**: an AI second pass proposes matches the rule-based pass
  missed. It only ever proposes; a bureau member still confirms each one.
- **Event automation**: rules can fire a set number of hours before an event, not
  only when registration closes.
- **Training focus marker**: an event can carry a kids / PN1 / PN2 priority-equipment
  focus, shown as a hatch on the instructor calendar.
- **Library**: create, rename, delete and copy folders and files.
- Licence scans are archived to `archived/licences/` once applied.

### Fixed
- Bank statements from real bank PDFs (multi-line entries, DD.MM.YY dates, +/- amount
  sign) are now read correctly; outgoing transfers are ignored.
- Event dates and times were treated as UTC instead of club time (Luxembourg), so
  registration open/close and automation fired up to 2 hours off.
- A registration-close rule could fire hours early on an event that also had an
  hours-before rule.
- Keyboard focus is now visible on every button; icons are hidden from screen
  readers; only one main landmark per page; reduced-motion is respected; dark mode
  no longer flashes light on load.
- Translation job timing out on long articles.
- Mail provider rotation now survives deploys; a missing Brevo key no longer breaks
  password reset.
- GDPR erasure: legacy erased members are visible for purge, and purging no longer
  fails on audit-log references.

### Data changes
- **Registration open/close times of all existing events were converted from
  "entered as club time, stored as UTC" to true UTC.** Times displayed in the app are
  unchanged; only the instant used for automation and open/closed status was
  corrected (+1h in winter, +2h in summer). 182 events on production.
