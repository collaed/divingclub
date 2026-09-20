# Changelog

What reached production, newest first. Conventions are in
`.kiro/steering/release-notes.md`. Entries before 2026-09-16 were not recorded.

## Unreleased

On `main` and staging, not yet on production.

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
automation changes above stayed on staging.

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
