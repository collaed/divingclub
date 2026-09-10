# Data model

Whole-project entity–relationship reference, kept so the **logic** of the model
can be reviewed and challenged — not just its current shape.

- **Source of truth:** the migrations in `database/migrations/` and the Eloquent
  models in `app/Models/`. This file is written by reading those; when they
  change, update the affected domain section here in the same PR.
- **Scope:** 82 application tables grouped into 9 domains below. Framework tables
  (`cache`, `jobs`, `job_batches`, `sessions`, `password_reset_tokens`,
  `failed_jobs`, `migrations`) and the Spatie permission tables
  (`permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`)
  are noted where they matter but not diagrammed.
- **How to read a diagram:** Mermaid `erDiagram`. `||--o{` = one-to-many,
  `||--||` = one-to-one, `}o--o{` = many-to-many (join table shown as its own
  entity). Only columns that carry meaning for the relationship or the review
  are listed — see the migration for the full column set.
- **Review markers:** each domain ends with **Design intent** (why it is shaped
  this way) and **Worth revisiting** (things that look like accidental
  complexity, drift, or a decision to re-take). These are prompts for
  discussion, not a backlog.

Status: **Pass 1** — domains 1 (Identity & access) and 2 (Certifications) are
fleshed out; domains 3–9 have the table inventory and a short intent note and
will be expanded in later passes.

---

## Domain map

| # | Domain | Tables |
|---|---|---|
| 1 | Identity & access | `users`, `user_emails`, `user_social_accounts`, `roles` (legacy) + Spatie `permissions`/pivots, `member_details`, `member_statuses`, `status_sets`, `status_set_members`, `guardian_links`, `parental_consents`, `push_subscriptions`, `login_records`, `page_visits`, `failed_login_attempts` |
| 2 | Certifications & prerogatives | `federations`, `certification_levels`, `user_certification_levels`, `diving_prerogatives`, `certification_prerogatives`, `member_licences` |
| 3 | Events & registration | `events`, `event_registrations`, `external_registrations`, `event_photos`, `seasons`, `season_patterns`, `season_holidays`, `instructor_availabilities`, `dive_sites`, `dive_groups`, `dive_group_members`, `dive_group_rules` |
| 4 | Trips & settlement | `trip_participants`, `trip_receipts` (+ trip-settlement columns on `events`) |
| 5 | Finance & membership fees | `membership_fees`, `membership_fee_components`, `payment_expected`, `bank_transactions` |
| 6 | Equipment | `equipment`, `equipment_loans`, `equipment_maintenance`, `equipment_maintenance_rules` |
| 7 | CMS, comms & content | `articles`, `article_translations`, `article_comments`, `article_images`, `newsletters`, `newsletter_approvals`, `email_templates`, `email_log`, `library_files`, `documents`, `links`, `social_publish_logs`, `theme_settings` |
| 8 | Governance (votes) | `votes`, `vote_options`, `vote_ballots`, `vote_groups`, `vote_tokens` |
| 9 | Compliance & ops | `medical_compliance_rules`, `gdpr_consents`, `audit_logs`, `trial_requests`, `club_partnerships`, `schedule_heartbeats`, `sync_runs`, `buddy_requests`, `buddy_responses` |

---

## 1. Identity & access

```mermaid
erDiagram
    users ||--|| member_details : "has one"
    users ||--o{ user_emails : "has many"
    users ||--o{ user_social_accounts : "has many"
    users ||--o{ push_subscriptions : "has many"
    users ||--o{ login_records : "has many"
    users ||--o{ page_visits : "has many"
    users }o--|| roles : "role_id (legacy)"
    users }o--o| member_statuses : "status_id"
    users }o--o| status_sets : "status_set_id"
    status_sets ||--o{ status_set_members : "has many"
    member_statuses ||--o{ status_set_members : "appears in"
    users ||--o{ guardian_links : "as guardian / as minor"
    users ||--o{ parental_consents : "minor_user_id / granted_by"

    users {
        id pk
        string username UK "nullable; legacy logins"
        string primary_email UK
        string password "nullable (OAuth-only / erased)"
        bigint role_id fk "NOT NULL -> roles"
        bigint status_id fk "nullable -> member_statuses"
        bigint status_set_id fk "nullable -> status_sets"
        timestamp email_verified_at
        timestamp last_seen_at
        boolean must_change_password
        softDeletes deleted_at
    }
    member_details {
        id pk
        bigint user_id fk "UNIQUE -> users"
        string first_name
        string last_name
        enum sex "M/F/X"
        date date_of_birth
        string certification_level "legacy free-text"
        json other_certifications "legacy"
        json training_enrollments
        json cotisation_years
        string preferred_language "5 chars"
        boolean public_photos_banned
        boolean show_on_public_site
    }
    user_emails {
        id pk
        bigint user_id fk
        string email UK
        boolean is_primary
        boolean is_verified
        boolean receive_mail "default true; false = login-only"
        string label
    }
    user_social_accounts {
        id pk
        bigint user_id fk
        string provider
        string provider_user_id
        string email
    }
    status_sets {
        id pk
        string name
        string slug UK
    }
    status_set_members {
        id pk
        bigint status_set_id fk
        bigint member_status_id fk
        boolean is_default
    }
    member_statuses {
        id pk
        string name
        string slug UK
        decimal fee_multiplier "4,2"
    }
    guardian_links {
        id pk
        bigint guardian_user_id fk
        bigint minor_user_id fk
        string relationship "parent / legal_guardian"
    }
    parental_consents {
        id pk
        bigint minor_user_id fk
        bigint granted_by fk "the guardian"
        string consent_type "events/photos/medical/general"
        boolean granted
        timestamp granted_at
        timestamp revoked_at
    }
    login_records {
        id pk
        bigint user_id fk "nullOnDelete"
        string ip_address
        string guard
        boolean remember
        timestamp created_at "no updated_at"
    }
    page_visits {
        id pk
        bigint user_id fk "nullOnDelete"
        string path "512"
        string route_name
        smallint status
    }
```

**Also here (not diagrammed):**

- **Authorisation** is Spatie `laravel-permission` (`permissions`, `roles` as
  the Spatie roles table after `2026_03_31_102717_rename_legacy_roles_table`
  renamed the old one, `model_has_roles`, `model_has_permissions`,
  `role_has_permissions`). `users.role_id` is the **pre-Spatie** single-role FK,
  still `NOT NULL` and still populated.
- `password_reset_tokens` (framework) — PK is `email`, holds one token per
  address; the reset link is emailed via `User::sendPasswordResetNotification()`
  (see `fix/password-reset-recipients-and-log`).
- `failed_login_attempts` — lockout throttling; `email` + `ip_address` +
  `attempted_at`, no FK to `users` (attempts may be for non-existent accounts).
- `sessions` (framework, DB session driver) — `user_id` indexed, not FK.

**Design intent**

- **`users` is the identity spine; `member_details` is the personal-data
  satellite.** 1:1, `member_details.user_id` unique, cascade-delete. Keeps the
  hot auth table narrow and puts GDPR-sensitive PII in one place to erase.
- **`user_emails` makes email many-per-user** with one `is_primary`.
  `users.primary_email` is a denormalised cache of that row for fast login
  lookup; `DivingClubUserProvider` resolves login against primary **or** a
  verified secondary **or** legacy `username`. `receive_mail` lets an address be
  usable for login but excluded from club mail.
- **`member_statuses` + `status_sets`** — a club defines its own status
  vocabulary (Active, Former, Honorary, …) with a `fee_multiplier`, and
  `status_sets` groups statuses into a scheme a member is placed in
  (`users.status_set_id`) with a default status per set.
- **Minors** are real `users` linked to guardian `users` via `guardian_links`
  (m:n, either direction), with `parental_consents` recording per-purpose,
  revocable sign-off.
- **`login_records` / `page_visits`** are append-only activity logs, `user_id`
  `nullOnDelete`, `created_at` only — deliberately not full models.

**Worth revisiting**

1. **`member_details` is a ~50-column fat table** mixing identity, address,
   emergency contact, diving history, equipment sizing, UI prefs
   (`show_icons`), and instructor fields. Several JSON blobs
   (`other_certifications`, `training_enrollments`, `cotisation_years`) are
   query-hostile. Candidates to split: `member_addresses`, `emergency_contacts`,
   `equipment_sizing`, and a typed `member_cotisations` table instead of a JSON
   year array.
2. **Two certification stores.** `member_details.certification_level` (free
   text) + `other_certifications` (JSON) are the legacy path; domain 2's
   `user_certification_levels` is the normalised path. Both are live. Pick one,
   migrate, and drop the other — the legacy columns are a reporting trap.
3. **Locale stored twice.** `member_details.preferred_language` and
   `users.preferred_locale` (added later, back-filled by
   `2026_09_07_223000_sync_preferred_locale_from_member_details`). One should be
   authoritative; the other is drift waiting to happen.
4. **`users.role_id` vs Spatie roles.** A `NOT NULL` legacy single-role FK
   living alongside the real m:n role system. Either formally demote it to a
   cached "primary role" with a comment, or remove it and adjust the code that
   still reads it.
5. **`member_statuses` overlaps `status_sets`.** Both model "what kind of member
   is this". If `status_sets` is the future, `member_statuses.fee_multiplier`
   and the direct `users.status_id` need a clear deprecation path so fee
   calculation reads one source.
6. **`parental_consents.granted_by` and `guardian_links.guardian_user_id`** can
   disagree (consent granted by someone not linked as a guardian). Worth a DB
   or model-level check that `granted_by` is an active guardian of
   `minor_user_id`.

---

## 2. Certifications & prerogatives

```mermaid
erDiagram
    federations ||--o{ certification_levels : "has many"
    federations ||--o{ member_licences : "has many"
    users ||--o{ member_licences : "has many"
    users }o--o{ certification_levels : "user_certification_levels"
    certification_levels }o--o{ diving_prerogatives : "certification_prerogatives"

    federations {
        id pk
        string acronym UK
        string full_name
        enum visibility "active / recognized / invisible"
    }
    certification_levels {
        id pk
        bigint federation_id fk
        string code "30; e.g. N1, OWD, P1"
        string name
        string category "diver / instructor / specialty"
        smallint rank "hierarchy within federation"
        string equivalence_group "cross-federation equivalence"
    }
    user_certification_levels {
        id pk
        bigint user_id fk
        bigint certification_level_id fk
        date obtained_date
        boolean is_primary "preferred display cert"
        int display_priority "nudged by user behaviour"
    }
    diving_prerogatives {
        id pk
        string code UK "10; PE-12, PA-20, ..."
        string name
        enum type "supervised / autonomous / guide / teach"
        smallint max_depth
        boolean requires_adult
    }
    certification_prerogatives {
        id pk
        bigint certification_level_id fk
        bigint diving_prerogative_id fk
    }
    member_licences {
        id pk
        bigint user_id fk
        bigint federation_id fk
        string licence_number
        string federation_key
        date licence_request_date
        boolean licence_request_pending
    }
```

**Design intent**

- **`federations` is the reference root** for anything cert-related. `visibility`
  (`active` / `recognized` / `invisible`, added
  `2026_03_19_080000...`) is per-club curation: `active` = a federation this
  club issues/manages, `recognized` = accepted from other clubs but not
  promoted, `invisible` = hidden from all pickers. `Federation::visible()` =
  active + recognized; `Federation::active()` = active only.
- **`certification_levels`** is the federation's own ladder (`rank` orders it,
  `category` buckets it). `equivalence_group` is the cross-federation bridge
  (e.g. FFESSM N2 ≈ LIFRAS P2 ≈ CMAS **) so rules can be written once against a
  group rather than per federation.
- **`user_certification_levels`** (m:n) is what a member actually holds.
  `is_primary` + `display_priority` drive how their headline cert is shown;
  `display_priority` is incremented when they set a primary, i.e. the UI learns
  their preference over time.
- **`diving_prerogatives`** decouples "what a diver may do" (depth, autonomy,
  supervision, teaching, adult-required) from the certification that grants it.
  `certification_prerogatives` maps levels → prerogatives, so eligibility checks
  (dive-group rules, event minimums) resolve to prerogatives, not to a specific
  federation's alphabet soup.
- **`member_licences`** is separate from certifications: it is the
  administrative federation membership/insurance (`licence_number`,
  `federation_key`, request-pending flag), one per `(user, federation)`.

**Worth revisiting**

1. **`certification_prerogatives` has no `timestamps()` and no model-level
   guard** beyond the composite unique. Fine as a pure pivot, but if
   prerogative mappings are ever club-editable it needs auditing like the rest.
2. **`equivalence_group` is a free string on `certification_levels`.** A typo
   silently breaks equivalence. A small `equivalence_groups` lookup (or an enum)
   would make the bridge safe and listable.
3. **`external_registrations.external_cert_level`** (domain 3) stores a
   *federation + level as text* ("LIFRAS P2★"). If partner clubs run the same
   system, this could resolve to a real `certification_levels` row (via
   `equivalence_group`) instead of a string, so external divers get the same
   eligibility checks as members.
4. **`member_details.certification_level` / `other_certifications`** — same
   legacy-vs-normalised duplication called out in domain 1. This domain is the
   intended home; the migration off the legacy columns hasn't happened.
5. **No `obtained_date` validation** against `member_details.date_of_birth` or
   against `rank` ordering (holding N3 without N2). Business rule that may belong
   in the model.

---

## 3. Events & registration  _(inventory — expand next pass)_

`events` is the other spine of the system. Members register via
`event_registrations`; partner-club divers via `external_registrations`
(→ `club_partnerships`, domain 9). `seasons` / `season_patterns` /
`season_holidays` are the recurring-schedule generator that stamps out events;
`season_patterns` carries template fields and a colour. `instructor_availabilities`
declares who can teach/guide on a date (optionally tied to an `event_id` and an
activity type). `dive_sites` is a reference table with plan images, fees and
emergency info; `dive_groups` + `dive_group_members` + `dive_group_rules` are the
per-event buddy/team planner, with rules expressed against prerogatives
(domain 2). `event_photos` holds the gallery (hash-dedup, face-detection flag,
`gdpr_consent`, media type).

**Early review flags:** `events` has grown many concern-specific column groups
(federation slots, trip settlement, dive pricing, dive days, van assignment,
season-pattern FK) — likely several of these want extraction into
`event_trip_settings` / `event_pricing`. `event_registrations` now also carries
`non_member_name` (guest registrations without a `user_id`), overlapping
conceptually with `external_registrations`.

## 4. Trips & settlement  _(inventory — expand next pass)_

`trip_participants` and `trip_receipts` hang off trip-enabled `events`. The
`TripSettlementService` splits shared costs (fuel by driving percentage, van,
receipts, instructor daily subsidy, prepaid amounts) across participants,
including non-members (`non_member_name`, nullable `user_id` on receipts).
Several incremental migrations reshaped this (`legs_driven` → `driving_percentage`,
`supervising_days`, `prepaid_amount`, `is_third_party` receipts).

**Early review flags:** settlement inputs are spread across `events`,
`trip_participants`, `trip_receipts` and config; a single documented formula
reference (or a computed `trip_settlements` snapshot table) would make audits
and disputes tractable.

## 5. Finance & membership fees  _(inventory — expand next pass)_

`membership_fees` + `membership_fee_components` define what a member owes for a
season (the fee system was reworked in `2026_03_17_221556`, then taper/proration
added in `2026_09_*`). `payment_expected` is the per-member expected line
(with provisional/refund-review flags); `bank_transactions` are imported
statement lines reconciled against expected payments (`statement_ref`).

**Early review flags:** `member_statuses.fee_multiplier`, season taper,
component `kind`, and proration interact in `FeeCalculationService` — the model
alone doesn't reveal precedence. Reconciliation between `bank_transactions` and
`payment_expected` looks string/ref-based rather than a modelled FK.

## 6. Equipment  _(inventory — expand next pass)_

`equipment` (heavily extended: short number, location, loanable, child/cold
water, detailed spec fields, `last_seen_at`), `equipment_loans` (with
`expected_return_date`), `equipment_maintenance` + `equipment_maintenance_rules`
(rule-driven "next maintenance due").

## 7. CMS, comms & content  _(inventory — expand next pass)_

`articles` (+ `article_types`, nullable author) with `article_translations`
(quality/stale fields — the AI translation pipeline), `article_comments`,
`article_images`. `newsletters` (+ `newsletter_approvals`, decorations,
`published_html`). `email_templates` + `email_log` (inbound + outbound;
outbound now also fed by the `MessageSent` listener in
`fix/password-reset-recipients-and-log`). `library_files` (visibility-scoped),
`documents` (polymorphic-ish, compliance/cert-type fields), `links`,
`social_publish_logs` (polymorphic `publishable`), `theme_settings` (key/value
club config).

**Early review flags:** `theme_settings` is an untyped key/value bag doing a lot
of load-bearing config work (see `SettingsController`'s allow-list). `documents`
vs `library_files` vs article images — three file-storage models with different
visibility rules.

## 8. Governance (votes)  _(inventory — expand next pass)_

`votes` (mode simple/election, `num_positions`, open/close window) → `vote_options`
→ `vote_ballots`. `vote_tokens` gives each eligible member a one-time,
anonymised ballot token (`vote_id` nullable after `2026_06_16_125812`).
`vote_groups` (added `2026_06_16_124726`) scopes an electorate.

**Early review flags:** anonymity model (`vote_tokens` unlinking voter from
`vote_ballots`) is the crux — worth documenting the exact guarantee. Nullable
`vote_tokens.vote_id` needs an explanation.

## 9. Compliance & ops  _(inventory — expand next pass)_

`medical_compliance_rules` (per federation, drives `MedicalComplianceService`),
`gdpr_consents`, `audit_logs` (retention-purged), `trial_requests` (public
"try a dive" funnel), `club_partnerships` (federated multi-instance API auth +
`external_registrations`), `schedule_heartbeats` (scheduler liveness, written by
`ScheduleHeartbeat::beat()` from `routes/console.php`), `sync_runs`
(legacy Joomla bidirectional sync), `buddy_requests` + `buddy_responses`
(member-to-member buddy matching, cert-level + max-buddies constrained).

---

## Next passes

- Flesh domains 3–9 to the depth of 1–2 (diagram + intent + review flags).
- Add a **cross-domain** diagram: the handful of FKs that cross domain
  boundaries (`user_id` everywhere, `event_id` into finance/trips/photos,
  `federation_id` into compliance).
- One consolidated **"decisions to re-take"** list pulled from every
  *Worth revisiting* section, ranked by blast radius.
