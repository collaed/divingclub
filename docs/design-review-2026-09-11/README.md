# Design review — 2026-09-11

Full-surface visual + structural review of DivingClub-Manager on staging
(`test.clubcep.eu`). One screenshot per screen, each with a design critique
focused on **structure and legibility**, not palette: how frames, rules,
separators, whitespace, grouping, field order/size, and input affordances
(datepicker, month picker, selects) could be reworked to cut density,
overload and confusion.

Captured as: **guest**, **bureau (eddy)**, and **impersonated regular member**.

## Method

Each `NN-slug.md` holds:
- **Screen** — route, role, state (menu open/closed, tab, etc.)
- **Reads as** — what a first-time user sees
- **Friction** — density / grouping / hierarchy / affordance problems
- **Restructure** — concrete, specific changes (reorder, group, separate,
  resize, swap control type, add/remove chrome)
- **Effort** — S / M / L

## Cross-cutting themes

_(updated as the review progresses)_

- **Centre-alignment everywhere on guest pages.** Splash, login, and register
  centre every element, leaving no left edge to scan and stretching short
  fields to full width. Prefer a centred *column* with left-aligned content.
- **Separator inflation.** `<hr>` used around single links (login), full
  card borders + coloured header bands used for plain input groups (dues).
  Rules and borders should mark real section boundaries, not decorate.
- **False affordances.** Bordered + coloured-header blocks that look
  collapsible but aren't (dues); bare text inputs where a date input belongs
  (trial "Preferred date", and elsewhere); full-width short fields.
- **Duplicated controls.** Two country pickers on one form (register); a
  15-tab language bar plus the nav language picker (article); two card-header
  styles on one page (contact, trial).
- **CMS/editor text leaking to guests.** "Edit this page in Administration…"
  renders on the public article.
- **Cookie banner floats mid-screen** as a fixed black bar that overlaps
  content and the footer on `/trial`, `/contact`, article pages. Dock it to
  `bottom: 0`. (Global partial — one fix covers all guest pages.)
- **Language mix in fee/domain terms.** "Cotisation", "Assurance
  Individuelle" untranslated on the English dues page.
- **Fold discipline on the splash.** Primary CTA below the fold at 1440×900
  because the hero heading is uncapped.

### Bureau / admin surface

- **Two full admin navigations on screen at once** — the left sidebar and
  the "Admin" mega-menu are the same 5 groups / ~24 links. Pick one primary;
  make the other contextual or remove it.
- **Inline editable `<select>`s in every table row** — members (role + status
  ×25), library (visibility), federations (name). Dozens of live dropdowns
  per page, unclear autosave. Move edits to a row drawer / ⋯ menu; rows
  read-only by default.
- **Tables that overflow the viewport** — members (actions column clipped),
  equipment ("last seen" clipped), roles matrix (technical_dir column
  clipped). Freeze the key column, scroll inside the table, or trim columns.
- **Missing `<x-sortable-th>`** on members, articles, equipment, payments,
  newsletters — `AGENTS.md` requires sortable headers on all data tables.
- **Columns full of "—" or one repeated value** — payments (Dû == Payé),
  articles (Expire), equipment (#/Lieu/Prêté à), trash (Deleted/By),
  logins (Pays half-resolved). Hide or merge low-signal columns; surface
  exceptions, not uniform "everyone OK" states.
- **Big-number / small-label KPI cards, inconsistently gridded** — dashboard
  (4 then 2 wider), payments ("En attente" appears twice). One equal grid,
  distinct labels, lead with the actionable figure.
- **Emoji in every admin heading and menu item** — decorative, inconsistent
  (two bar-chart glyphs for three different stats pages), noise at small
  size. Drop or commit to one 16px icon set that disambiguates.
- **English strings on the FR admin** — analytics page, doc-dispatch,
  library, event-form deposit rows, roles matrix (raw permission keys),
  calendar month title + weekday headers, several column headers.
- **Bulk / seed data with null actor + identical timestamp** dumped into
  user-facing lists — 107 trash rows, 8 identically-timed articles,
  duplicate overlapping seasons. Group batches; flag overlaps.
- **Oversized rich-text editors** on the event and article forms — full
  toolbar + ~300px body for fields that are usually 1–2 sentences; they
  separate related fields by a screenful. Default small, expand on demand.
- **Card layouts where a table row would do** — votes (3 tall cards),
  newsletters/seasons (huge empty page under 3–4 rows).
- **Config paths / env-var names shown to bureau users** — "config/
  activity_types.php" on the event form, "UMAMI_SHARE_URL … in the app
  config" on analytics.
- **Privileged fields not visually privileged** — profile's "Bureau Master
  uniquement" block is a plain grey label, not a distinct locked zone.

### Member-facing surface

- **Localisation is broken across the board.** With a member whose locale is
  Italian, almost every screen mixes 3–4 languages: nav in EN+IT, page chrome
  in IT, form labels flipping EN/IT mid-form, status/type values hard-coded in
  French, headings and empty-states in English. This is the **single biggest
  member-experience problem** — pervasive, and it undermines every other fix.
  Likely causes: strings not wrapped in `__()`, enum/status values stored as
  French display text, and some partials hard-coded.
- **Members get admin screens with a banner.** `/availability`,
  `/documents`, `/members` (directory), and `/profile` render the
  bureau/admin UI with one "read-only" line or a couple of fields swapped.
  Members need purpose-built, lighter views, not the admin form greyed out.
- **The logged-in member home is the marketing home.** Empty hero carousel,
  "Welcome to DivingClub", federation links in the prime slot — nothing
  personalised (next session, dues, votes, medical validity).
- **Split controls for one value** recur on member forms too — buddies
  "Where?" (select + free text), "When?" (date + free-text time); same
  pattern as register/event-location.
- **Post-forms always expanded** on browse pages (buddies, classifieds) —
  an 8-field form dominates a page whose list is empty.
- **Empty states are a bare sentence in a box** — classifieds, buddies,
  gallery — no explanation of the feature, CTA not in the empty region.
- **Duplicated management UIs** — medical documents managed in both
  `/documents` ("My Documents" + "Manage in Profile") and the profile's
  "Certificat médical" tab.
- **Broken placeholder imagery** — every gallery album shows the same garbled
  "stack of photos" placeholder instead of a cover photo.
- **GDPR page**: consent items (the legally-weighted strings) are the
  untranslated ones; consent is grant-only (no equal-ease withdrawal);
  irreversible erasure styled as a plain button.
- **Calendar** (members see the same one as bureau): EN month title +
  weekday headers regardless of locale; "Annulé/Annullato" badge sits
  outside the event chip.

## Index

### Guest (not logged in)

| # | Screen | Route | Key issue | Effort |
|---|--------|-------|-----------|--------|
| 01 | [Splash / landing](01-guest-splash.md) | `/` | CTA below the fold; heading uncapped; buried stats | M |
| 02 | [Login](02-login.md) | `/login` | `<hr>`-fenced forgot link; form floats; social = primary weight | S |
| 03 | [Register](03-register.md) | `/register` | ✗ marks read as errors; no sections; two country controls | M |
| 04 | [Try Diving](04-trial.md) | `/trial` | form ~1100px down; loud health callout; text-input date | M |
| 05 | [Dues calculator](05-dues.md) | `/dues` | 3 bordered blocks read as 3 forms; no pinned total; FR/EN mix | L |
| 06 | [Article "Our Values"](06-article-values.md) | `/news/{slug}` | 15-tab language bar dominates; CMS text public; repeated H2 | M |
| 07 | [Contact](07-contact.md) | `/contact` | flat detail block; 3× "View on Map" no map; column imbalance | S–M |

### Bureau (eddy)

| # | Screen | Route | Key issue | Effort |
|---|--------|-------|-----------|--------|
| 08 | [Admin dashboard](08-admin-dashboard.md) | `/admin/dashboard` | 4+2 uneven KPI grid; money cards biggest but lowest; empty below fold | M |
| 09 | [About dropdown](09-about-dropdown.md) | nav | lone "Cotisations" fenced below a divider; decorative emoji | S |
| 09b | [Admin mega-menu](09b-admin-megamenu.md) | nav | duplicates the sidebar; uneven 3+2 columns; 27 emoji, no system | M |
| 10 | [Members list](10-admin-members.md) | `/admin/members` | ~50 inline selects; actions column clipped; role shown twice | L |
| 11 | [Member profile / edit](11-member-profile.md) | `/admin/members/{id}/profile` | photo widget in summary card; 3 concern-levels in "Info"; 8 flat tabs | M–L |
| 12 | [Event calendar](12-calendar.md) | `/events` | EN month title on FR page; "Annulé" badge outside chip; empty grid | M |
| 13 | [New event form](13-event-create.md) | `/events/create` | ~35 fields, no sections; huge editor; 3 date styles; config path shown | L |
| 14 | [Instructor planning](14-availability.md) | `/availability` | 22-chip picker wall; 3 stacked banners; colour overloaded | M–L |
| 15 | [System settings](15-admin-settings.md) | `/admin/settings` | 6 tabs × 5 accordions nested; 36 open inputs; editable slugs | M |
| 16 | [Federations](16-admin-federations.md) | `/admin/federations` | same 11 federations listed twice (table + accordion); 2 intro paras | M |
| 17 | [Equipment inventory](17-admin-equipment.md) | `/admin/equipment` | 200 near-identical tank rows; epoch dates; last column clipped | M–L |
| 18 | [Payments & dues](18-admin-payments.md) | `/admin/payments` | "En attente" ×2 in KPIs; 25 identical green rows; no date column | M |
| 19 | [Seasons](19-admin-seasons.md) | `/admin/seasons` | duplicate overlapping season names; single-year column ambiguous | S–M |
| 20 | [Articles list](20-admin-articles.md) | `/admin/articles` | 13 filter buttons over 2 rows; two boolean styles; no sort | M |
| 21 | [New / edit article](21-article-create.md) | `/admin/articles/create` | language invisible; FR/EN labels; always-on "Attach Vote"; 2 image UIs | M |
| 22 | [Document Library](22-admin-library.md) | `/admin/library` | "1908 files" but 1 shown; always-open upload form; no folder counts | M |
| 23 | [Newsletters list](23-admin-newsletters.md) | `/admin/newsletters` | "0/3 approvals" on sent rows; random order; no send metrics | S–M |
| 24 | [Email system](24-admin-email.md) | `/admin/email` | no template list; log squeezed into half-width; column height mismatch | M |
| 25 | [Votes list](25-admin-votes.md) | `/admin/votes` | tall cards for 1 stat line; "tokens/ballots" opaque; no dates | S–M |
| 26 | [Recycle bin](26-admin-trash.md) | `/admin/trash` | 107 bulk-deleted rows, null actor; "Cancelled" a separate 7th tab | M |
| 27 | [Login history](27-admin-logins.md) | `/admin/logins` | 25 rows of one admin; geo half-resolved shows "—"; full IPv6 ×20 | M |
| 28 | [Site analytics](28-admin-analytics.md) | `/admin/analytics` | env-var setup instructions shown to bureau; all English | S–M |
| 29 | [Tracked docs list](29-doc-dispatch-index.md) | `/admin/document-dispatch` | bilingual; bare "Nothing sent yet"; 4 names for the feature | S |
| 30 | [New tracked send](30-doc-dispatch-create.md) | `/admin/document-dispatch/create` | 200 raw checkboxes, no search; no confirm/preview before send | M |
| 31 | [Own profile](31-own-profile.md) | `/profile` | members get the full admin edit form; no self-service framing | M–L |
| 32 | [Roles & permissions](32-admin-roles.md) | `/admin/roles` | 234-checkbox matrix, no groups; overflows (technical_dir clipped); raw keys | M–L |

### Impersonated regular member (locale = IT)

| # | Screen | Route | Key issue | Effort |
|---|--------|-------|-----------|--------|
| 40 | [Member home](40-member-home.md) | `/` | empty hero carousel; marketing home, not personalised; 4 languages | M–L |
| 41 | [Members directory](41-members-directory.md) | `/members` | former members mixed in; no sort; FR status values on IT page | M |
| 42 | [Trombinoscope](42-members-trombinoscope.md) | `/members/trombinoscope` | no search/filter (unlike list view); cert badges no legend | M |
| 43 | [Member calendar](12-calendar.md) | `/events` | (see 12 — identical, IT locale: EN month + weekday headers, FR chips) | — |
| 44 | [Event detail](44-member-event-detail.md) | `/events/{id}` | 4 map controls on one card; "register another" as loud as self-register | M |
| 45 | [Instructor planning (read-only)](45-member-availability.md) | `/availability` | members get the full staffing UI; see 14 for structure | S–M |
| 46 | [Classifieds](46-member-classifieds.md) | `/classifieds` | all-English; search box over empty list; bare empty state | S |
| 47 | [Looking for Buddies](47-member-buddies.md) | `/buddies` | all-English; 8-field form always open; split Where/When controls | S–M |
| 48 | [Documents](48-member-documents.md) | `/documents` | admin library UI; member's medical-cert status buried below folders | M |
| 49 | [Photo Gallery](49-member-gallery.md) | `/gallery` | every album cover is a broken placeholder; "Fosse" ×3 undisambiguated | S–M |
| 50 | [Own profile](50-member-profile.md) | `/profile` | full 8-tab admin form; "Membership Status" self-editable; labels flip language | M–L |
| 51 | [Privacy / GDPR](51-member-privacy.md) | `/privacy` | consent item names untranslated; grant-only consent; erasure = plain button | M |

## Highest-leverage fixes (across the whole review)

1. **Localisation sweep** (member surface) — pervasive multi-language mixing;
   nothing else in the member experience reads as finished until this is fixed.
2. **Pick one admin navigation** — sidebar *or* mega-menu, not both; 4 balanced
   groups; consistent labels; drop decorative emoji.
3. **Tables: read-only rows + ⋯ menu, sortable headers, exceptions-only
   columns, no viewport overflow** — members, equipment, payments, articles,
   library, roles all share these faults.
4. **Section the two monster forms** — event-create (~35 fields) and
   member-profile (8 flat tabs) — into collapsible groups with sane defaults.
5. **Purpose-built member views** for `/profile`, `/documents`,
   `/availability` instead of the admin screens with fields hidden.
6. **Dock the cookie banner**; **stop leaking CMS text / config paths / env
   var names** to end users.
