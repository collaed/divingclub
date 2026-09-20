## Move MailBalancer to an OS-level shared component (deferred)

**Idea:** Staging and production run on the same Hetzner host but each has its
own database, so each tracks its own daily per-provider mail counts — even
though the real Resend/Brevo accounts (API keys) and the local Postfix relay
(`mailjet` provider) are actually shared between both apps. On 2026-09-14 a
~84-email staging test burned most of the day's real 100/day Resend quota
before production's own dispatch even started, and production's own counters
never saw it coming since it lived in a separate database.
- **Read the API's real return, not just a periodic ping.** Today,
  `MailBalancer::resendQuotas()` gets real quota/rate-limit numbers via a
  separate synthetic `/emails/batch` ping (cached 5 min) — the actual send
  path (`Illuminate\Mail\Transport\ResendTransport` via the `resend/resend-php`
  SDK) discards the HTTP response headers where that data lives, so no
  per-send quota signal reaches the app today.
- **Make it a genuinely OS-level component**, e.g. a small daemon/service or a
  shared SQLite/file store under `/opt/deploy/` outside either app's own
  database, so staging and prod both read/write the same counters — matching
  how Postfix itself is already a single shared system service.
- **Feed the admin dashboard** ("Email Sending Quota" panel) from that shared,
  live-queried state instead of (or in addition to) the durable per-app
  `MailSendStat` table.
- **Selection strategy:** replace "least-used-so-far" with a looser
  round-robin — one send to each provider in turn before repeating — or a
  random choice weighted by each provider's remaining capacity, so a burst
  naturally spreads without needing perfectly accurate live counts.

**Status:** Deferred. Current fix (2026-09-14): `MailBalancer` now derives its
per-provider daily counts and rotation purely from the durable `MailSendStat`
table (survives a deploy's `artisan optimize:clear`, which previously reset
everything to zero every deploy) — this closed the "resets on every deploy"
bug but not the "staging and prod don't see each other's usage" gap described
above.

## Auto-propose insurance choice from a payment's amount (deferred)

**Idea:** When a membership payment comes in (or a bureau member is entering
one), the base cotisation + FFESSM licence + FLASSA are already fully
determined by the member's status (de droit/externe) and age — nothing to
guess there (`FeeCalculationService`). The only real unknown left is which
insurance tier they picked, and `config('cotisation.insurance')` is just a
flat table of amounts. So: `received_amount − known_base` should match
exactly one insurance tier's amount; when it does, pre-check that option in
the membership order UI (and use it as a match-reason hint in bank
reconciliation). When the delta matches zero or more than one tier, leave it
to manual selection — propose, never auto-confirm, same rule as the AI bank
matching.

The base table for this now exists as a test: `DuesCalculatorControllerTest::realWorldCases()`
lists the expected total for every (status family, age band) — Membre de droit family
120 / Externe 130, halved under 18, plus the FFESSM licence band and FLASSA from 18. A
payment minus that base is what the insurance choice has to explain.

**Status:** Deferred, not started. Proposed shape: a
`FeeCalculationService::identifyInsuranceFromAmount(User $user, string $seasonYear, float $amount): ?string`
returning the matching insurance slug or null.

## Event registrations requiring compliance from more than one federation (deferred)

**Idea:** An event should be able to require a valid licence + medical cert
for two or more federations at once on a single registration (not just one).
`MedicalComplianceService::evaluateCertificate()` already computes expiry
per-federation internally (`$perFed`) but only persists the aggregated
"latest across all active federations" date plus a human-readable
`compliance_notes` string — there's no way today to check "is this member
compliant for FFESSM specifically, as of this event's date" rather than
"today, across whichever federation is most generous."

- Persist the per-federation breakdown structurally (e.g. a
  `medical_cert_federation_expiries` table: `document_id`, `federation_id`,
  `expiry_date`) instead of only the notes string, so a specific
  federation + date can be queried directly
  (`isCompliantForFederation(User, Federation, Carbon $atDate)`).
- Model an event's federation requirements as **rows, not a column** — a
  pivot table `event_federation_requirements` (`event_id`, `federation_id`),
  zero-to-many rows per event — mirroring how `MedicalComplianceRule`
  already models federation rules. An event needing a third or fourth
  federation later is then just new rows, never a migration. An event with
  zero rows behaves exactly as today (no federation-specific check at all).
- Registration UI renders one compliance line (licence + medical cert) per
  required federation, each checked independently.

**Status:** Deferred, not started. Depends on the FeeCalculationService item
above only in that both touch registration/payment UI — otherwise independent;
either can be built first.

## Equipment-priority focus marker on training events (done)

Shipped 2026-09-17/18: a nullable `focus_group` column on `events`
(`kids`/`pn1`/`pn2`), rendered on the instructor-availability calendar
(`resources/views/availability/index.blade.php`) as a diagonal hatch
(`.focus-*` in `resources/scss/partials/_planning.scss`) mixed into the
event's own activity-type colour, reusing the matching `pool_kids`/`pool_pn1`/
`pool_pn23` accents from `config/event_focus_groups.php` so the same focus
reads the same way everywhere. Live on staging and production.

**Status:** Done.

## Offsite backup via Google Drive (deferred)

**Idea:** Automate offsite backup to Google Drive instead of manual SFTP.
- Pros: No external server to manage, OAuth-based auth, familiar UI
- Cons: Rate limits, quota management, requires Google account
- Decision: Use SFTP drop-box (ecb.pm) instead for now (more control, simpler ops)
- If revisited: Use rclone + Google Drive API with incremental syncs

**Status:** Deferred. Current approach: SFTP to ecb.pm drop-box.
