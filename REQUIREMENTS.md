# DivingClub — Screen-by-Screen Requirements

Comprehensive specification of every screen in the application, documenting what was implemented and the intended behavior. Use this to verify alignment with the original vision.

---

## 1. Public Screens (No Login Required)

### 1.1 Welcome / Landing Page (`/` when not logged in → `welcome.blade.php`)
- Full marketing-style landing page for the club
- Club branding, feature highlights, call-to-action to register/login
- Accessible without any authentication

### 1.2 About Pages (Public, via "About" dropdown)
Seven pinned, public articles accessible to everyone (including non-logged-in visitors) via the "About" nav dropdown:

- **Training Schedule** (`/article/schedule`) — Pool session times, locations, levels, open water season, holiday breaks. Stub with sample table, editable by admin.
- **Our Values** (`/article/values`) — Club identity: safety, inclusivity, environment, learning, community. Stub with bullet points.
- **Contact & Social Networks** (`/article/contact-info`) — Email, postal address, Facebook, Instagram, WhatsApp group link, training locations. Stub with placeholders.
- **Club History** (`/article/history`) — Founding story, key milestones. Stub with timeline placeholders.
- **The Bureau** (`/article/bureau`) — Current elected board members (president, VP, treasurer, secretary, technical director). Stub with table. Links to Document Library for meeting minutes.
- **Our Members** (`/article/member-figures`) — Membership breakdown by gender, nationality, language, certification level. Stub with section headers, admin fills in from Dashboard CSV export.
- **Our Instructors** (`/article/instructors`) — Dynamic page: shows instructor profile cards pulled from the database (bio, specialties, motivation). Instructors fill in their profile in My Profile → Diving tab.

All stubs are created by `PinnedArticleSeeder` and editable in Administration → Articles. They use `is_public=true` and `sort_order` for ordering.

### 1.3 Home Page (`/` when logged in → `home.blade.php`)
- Shows published articles as cards, newest first
- Each card has: type-colored left border, type badge (icon + label), title, 300-char excerpt, "Read more" link, date, author
- Trip proposals show a 🗳️ Vote badge
- Classifieds are excluded from the home feed (they have their own section)
- Sidebar with upcoming events and quick links
- Only shows active articles (not expired, published)

### 1.4 Article Detail (`/article/{slug}` → `cms/article.blade.php`)
- Type-colored background header bar (color configurable by admin per type)
- Type badge with icon and label
- Featured image (if set)
- Full article body (HTML, sanitized by HTMLPurifier)
- Image gallery section: images displayed in responsive grid using layout hints (full=col-12, half=col-md-6, third=col-md-4), each with optional caption
- Embedded vote for trip proposals: if article has an attached vote, the vote form/results appear inline
- Prev/Next navigation at bottom: prioritizes same-type articles, falls back to overall chronological
- Threaded comments section (logged-in members only):
  - Max 3 levels deep
  - Reply toggle opens inline form
  - Comment authors and admins can delete
  - Comments sanitized by HTMLPurifier (only p, br, strong, b, em, i, a[href])
- Expired articles show an expiry badge

### 1.5 Contact Page (`/contact`)
- Static contact information for the club

### 1.6 Dues Calculator (`/dues`)
- Public tool to estimate membership fees
- Fields: first name, last name, season year, member status
- Calculates: base fee × status multiplier × age discount + optional add-ons
- Shows SEPA QR code for payment
- All fields have @error validation feedback

### 1.7 Offline Page (`/offline`)
- PWA fallback page shown when the user is offline

---

## 2. Authentication Screens

### 2.1 Registration (`/register` → `auth/register.blade.php`)
- Fields: first name, last name, email, password, password confirmation
- Honeypot CAPTCHA: hidden `website` field (bots fill it → rejected) + `_ts` timestamp (submitted in <3s → rejected)
- OAuth buttons: Google, Facebook, Microsoft (+ any other configured providers)
- All fields have @error validation feedback
- After registration: email verification required before accessing member features

### 2.2 Login (`/login` → `auth/login.blade.php`)
- Email + password
- Account lockout after failed attempts (15 min)
- OAuth login buttons
- "Forgot password" link
- @error validation feedback

### 2.3 Forgot Password (`/forgot-password`)
- Email field to request password reset link
- @error validation feedback

### 2.4 Reset Password (`/reset-password/{token}`)
- New password + confirmation
- @error validation feedback

### 2.5 Email Verification (`/email/verify`)
- Notice that verification email was sent
- Resend button

---

## 3. Member Screens (Requires Login + Verified Email)

### 3.1 Profile (`/profile` → `profile/show.blade.php`)
Tabbed interface with:

**Info Tab** — Name, email, phone, address, nationality, date/place of birth, sex, profile photo upload/delete. @error on all fields.

**Private Tab** — Emergency contact, additional phone numbers, address details. Bureau-editable fields. @error on all fields.

**Diving Tab** — Federation licences, certification levels (add/edit/remove/set primary), brevet dates, document scans. Supports multiple federations simultaneously. @error on all fields. For instructors/assistants: additional "Instructor Profile" section with bio, specialties, and motivation fields — visible to all members on the Instructors page.

**Medical Tab** — Medical certificate upload, verification status, expiry tracking. @error on all fields.

**Language Tab** — Preferred communication language selector (11 locales). @error on select.

**Registrations Tab** — List of event registrations with status.

**Equipment Tab** — Equipment currently on loan to this member.

**Renewal Tab** — Membership renewal status and payment info.

### 3.2 Events List (`/events` → `events/index.blade.php`)
- Calendar view: month/week/day toggle
- Event cards with: title, date/time, location, type, instructor, registration count/max
- Filter by type

### 3.3 Event Detail (`/events/{event}` → `events/show.blade.php`)
- Full event info: title, description, date/time, location (with Google Maps link), instructor, WhatsApp group link
- Registration button (or waiting list if full)
- Cancel registration button
- Medical gate: blocks registration if member lacks valid medical certificate (except social events)
- Event photos section: upload (with consent check) and gallery
- Registration list (for instructors/admin)

### 3.4 Event Create/Edit (`/events/create`, `/events/{event}/edit` → `events/form.blade.php`)
- Fields: title, description, type, date/time, location, max participants, instructor, WhatsApp link, add-ons
- @error on all fields

### 3.5 Classifieds (`/classifieds` → `classifieds/index.blade.php`)
- "My Classifieds" section at top: table with title, expiry date, status badge (Active/Expiring soon/Expired), action buttons (Edit/Delete/Extend/Renew)
- "All Classifieds" section below: cards showing all active classifieds from all members
- Each card: title, excerpt, photo thumbnail, author, days remaining
- "Post a Classified" button

### 3.6 Classified Form (`/classifieds/create`, `/classifieds/{article}/edit` → `classifieds/form.blade.php`)
- Info banner explaining 30-day auto-expiry
- Fields: title, description (rich text editor), photo upload
- @error on all fields
- Classifieds are stored as articles with `article_type='classified'`, `is_public=false`, `expires_at` = now + 30 days

### 3.7 Document Browser (`/documents` → `documents/index.blade.php`)
- Browse public files uploaded by the bureau
- Folder navigation sidebar (only folders containing public files)
- Table: filename (with type icon), size, date, download button
- Files organized by folder

### 3.8 Members Directory (`/members` → `members/directory.blade.php`)
- Searchable list of club members
- Shows name, status, certification level

### 3.9 Trombinoscope (`/members/trombinoscope` → `members/trombinoscope.blade.php`)
- Photo grid of all members with profile photos

### 3.10 GDPR / Privacy (`/privacy` → `gdpr/consents.blade.php`)
- Consent toggles (photo publication, etc.)
- Download My Data (JSON export)
- Request Data Erasure link

### 3.11 GDPR Erasure Confirmation (`/privacy/erasure` → `gdpr/erasure-confirm.blade.php`)
- Lists what will be deleted/anonymized
- Confirmation checkbox + button
- @error on confirmation field

### 3.12 Vote Page (`/vote/{token}` → `vote/show.blade.php`)
- No login required — token is authentication
- Vote title, description
- Options displayed as:
  - Radio buttons (single-select) OR checkboxes (multi-select, when `allow_multiple` is enabled)
- Messaging adapts to mode:
  - Simple + changeable: "You can change your vote until it closes"
  - Simple + not changeable: "Your vote cannot be changed once submitted"
  - Election: "Your vote is anonymous and irreversible"
- If voter already voted and `allow_change` is true: shows current selections, "Update Vote" button
- If voter already voted and `allow_change` is false: shows "already voted" message
- If `is_public` is true: live results shown as progress bars with percentages and vote counts
- Vote Closed page when vote is past `closes_at`
- Thank You page after casting

---

## 4. Administration Screens (Requires `bureau_master` or `bureau_member` Role)

### 4.1 Dashboard (`/admin/dashboard` → `admin/dashboard/index.blade.php`)
- Statistics cards: total members, new this year, events held, revenue collected
- Charts (Chart.js): members over time, members by status, monthly participation, events by type, equipment by status
- Quick actions: manage members, create event, send email
- CSV export

### 4.2 Members Management (`/admin/members` → `admin/members/index.blade.php`)
- Searchable, filterable table of all members
- Columns: name, email, status, role, verified, joined date
- Filter by: role, status, verified/unverified
- Actions: view profile, impersonate, send password reset
- Impersonation: admin can act as any member (with banner showing impersonation is active)
- Medical Export dropdown:
  - **Member List (CSV)**: semicolon-delimited, UTF-8 BOM, columns: Date Demande (empty), NOM, Prénom, Date de naissance, sexe, n° Rue, Pays, CP, Localité, Date Examen Médical
  - **Certificates (ZIP)**: all current medical certificates in a ZIP, each file named `LASTNAME Firstname member#.ext`

### 4.3 Articles Management (`/admin/articles` → `admin/articles/index.blade.php`)
- Type filter bar: clickable badges for each of the 11 article types
- Table: title, type badge, published status, date, author
- Create/Edit/Delete actions

### 4.4 Article Form (`/admin/articles/create`, `/admin/articles/{article}/edit` → `admin/articles/form.blade.php`)
- Fields: title, slug (auto-generated), body (rich text), article type selector (11 types), published toggle, public toggle
- Vote attachment: dropdown to link an existing vote (for trip proposals)
- Featured image upload
- Image gallery section:
  - Add multiple images with: file upload, caption text, layout hint selector (Full width / Half / Third)
  - "Add image" button to add more rows dynamically
  - Existing images shown with delete button
- @error on all fields

### 4.5 Seasons Management (`/admin/seasons` → `admin/seasons/index.blade.php`)
- List of seasons with year, status (active/inactive), event count
- Create new season

### 4.6 Season Detail (`/admin/seasons/{season}` → `admin/seasons/show.blade.php`)
- Weekly patterns: define recurring events (day, time, type, instructor)
- Holidays/breaks: dates to skip during generation
- Generate events: preview → confirm workflow
- Clone from previous season
- Activate season

### 4.7 Season Form (`/admin/seasons/create` → `admin/seasons/form.blade.php`)
- Season year, start/end dates
- @error on all fields

### 4.8 Season Preview (`/admin/seasons/{season}/preview` → `admin/seasons/preview.blade.php`)
- Shows all events that will be generated
- Skipped dates (holidays) shown in red
- Confirm button to generate

### 4.9 Payments (`/admin/payments` → `admin/payments/index.blade.php`)
- Table of all payments: member, amount, status, communication string, date
- Filter by status (pending/paid/overdue)
- Calculate/generate fees per member

### 4.10 Bank Reconciliation (`/admin/payments/reconciliation` → `admin/payments/reconciliation.blade.php`)
- Import bank statement (paste text, one transaction per line)
- Auto-match transactions to payments using fuzzy matching on communication strings
- Matched/unmatched/ignored tabs
- Confirm match or ignore actions

### 4.11 Payment Components (`/admin/payments/components` → `admin/payments/components.blade.php`)
- Manage optional add-on fee components
- Add/delete components with label and amount

### 4.12 Equipment Inventory (`/admin/equipment` → `admin/equipment/index.blade.php`)
- Table: item name, type, serial, status, current loan
- Create new equipment

### 4.13 Equipment Detail (`/admin/equipment/{equipment}` → `admin/equipment/show.blade.php`)
- Full equipment info, photo, purchase date
- Loan management: loan to member / return
- Loan history
- Maintenance schedule: upcoming tasks, complete maintenance (auto-schedules next)
- Component tracking

### 4.14 Equipment Form (`/admin/equipment/create` → `admin/equipment/form.blade.php`)
- Fields: name, type, serial, purchase date, notes
- @error on all fields

### 4.15 Email System (`/admin/email` → `admin/email/index.blade.php`)
- Compose: subject (with variable support: {{first_name}}, {{last_name}}, etc.), body (rich text), recipient group selector (6 groups)
- Templates: save/load/edit/delete reusable templates
- Preview before sending
- Send log: table of all sent emails with status, date, recipient

### 4.16 Votes Management (`/admin/votes` → `admin/votes/index.blade.php`)
- Table: title, mode, status (draft/open/closed/cancelled), dates, ballot count

### 4.17 Vote Detail (`/admin/votes/{vote}` → `admin/votes/show.blade.php`)
- Vote info, options, current results (bar chart)
- Actions: open, close, cancel
- Generate tokens button (creates one token per eligible member)
- Token list with status (used/unused)

### 4.18 Vote Form (`/admin/votes/create` → `admin/votes/form.blade.php`)
- Fields: title, description, mode (simple/election), opens at, closes at
- Checkboxes: allow multiple selections, allow vote change (checked by default), show results publicly
- Options: dynamic list of text inputs (minimum 2, add more button)
- @error on all fields

### 4.19 Links Management (`/admin/links` → `admin/links/index.blade.php`)
- Manage quick links shown in sidebar
- Add/delete with: label, URL, order
- @error on fields

### 4.20 Document Library (`/admin/library` → `admin/library/index.blade.php`)
- FileGator-equivalent file management for the bureau
- Folder sidebar with navigation, create new folder
- Upload: multi-file, optional description, public/private toggle
- File table: name (with type icon), size, visibility toggle button, upload date/uploader, delete
- Download any file
- Toggle visibility: click Public/Private button to flip
- Private files: only visible to bureau (archival, meeting minutes, internal docs)
- Public files: browsable by all members via Info → Documents

### 4.21 Audit Logs (`/admin/audit-logs` → `admin/audit-logs/index.blade.php`)
- Table: user, action, model, changes, timestamp
- Filter by: user, action, role, date range
- Purge old entries (with confirmation)

### 4.22 Annual Report (`/admin/annual-report` → `admin/annual-report.blade.php`)
- Printable annual report with statistics
- Member counts, event summary, financial summary

### 4.23 Settings (`/admin/settings` → `admin/settings/index.blade.php`)
Accordion sections:

**Federations** — Add/edit/delete diving federations. Each has: name, acronym, country. Pre-seeded with 11 federations.

**Member Statuses** — Manage statuses with French slugs and fee multipliers. Add/edit.

**Membership Fees** — Set absolute fee per status per season year. Table with year × status grid.

**Medical Compliance Rules** — Define required certificates per federation, age bracket, cert type, validity in months. Add/edit/delete.

**Equipment Maintenance Rules** — Define maintenance schedules per equipment type. Interval in months, mandatory flag. Add/edit/delete.

**Theme & Appearance** — 
- Preset selector: Ocean, Coral, Lagoon, Abyss, Tropical, Arctic
- Custom color pickers: primary, secondary, accent, header gradient start/end, footer background
- Article type background colors: one color picker per article type (news, history, safety, training, regulation, trip_report, trip_proposal, environment, gear, classified, faq)
- Branding: logo emoji, logo text, club full name
- Layout: width selector, header bubble animation toggle
- Logo upload

**Club & Finance (Banking)** — Club IBAN, BIC, beneficiary name for SEPA QR codes.

**API Keys & Configuration** — Status display for all integrations (OAuth providers, Maps, Translation, OCR, LLM). Keys are in .env, this page shows configured/not-configured status.

### 4.24 Admin Guide (`/admin/guide` → 14 pages)
In-app documentation covering:
1. System Overview
2. First Steps After Deployment
3. Managing Members
4. Seasons & Events
5. Medical Compliance
6. Payments & Fees
7. Equipment Inventory
8. Email System
9. Voting System
10. GDPR & Privacy
11. Settings & Configuration
12. API Keys & OAuth Setup
13. Backups & Maintenance
14. Troubleshooting

Each page has prev/next navigation and a sidebar table of contents.

---

## 5. Navigation & Layout

### 5.1 Main Layout (`components/layout.blade.php`)
- Responsive navbar with: logo, Home, Events, Members, Classifieds (for logged-in), Dues Calculator
- Admin dropdown (for bureau roles): Dashboard, Members, Articles, Seasons, Payments, Equipment, Email, Votes, Links, Audit Logs, Settings, Guide
- User dropdown: Profile, Privacy, Logout
- Language switcher (11 locales)
- Theme-aware: all colors from admin-configured theme
- PWA installable

### 5.2 Footer
- Club info, links
- Cookie consent banner (GDPR)

---

## 6. Cross-Cutting Concerns

### 6.1 Article Types (11 types)
| Type | Icon | Default Color | Usage |
|------|------|---------------|-------|
| news | 📰 | #0d6efd | General club news |
| history | 🏛️ | #6f42c1 | Club history articles |
| safety | ⚠️ | #dc3545 | Safety bulletins |
| training | 🎓 | #198754 | Training materials |
| regulation | 📋 | #6c757d | Rules and regulations |
| trip_report | ✈️ | #0dcaf0 | Past trip reports |
| trip_proposal | 🗺️ | #fd7e14 | Future trip proposals (with vote) |
| environment | 🌊 | #20c997 | Environmental topics |
| gear | 🤿 | #495057 | Gear reviews/info |
| classified | 🏷️ | #ffc107 | Member classifieds (separate section) |
| faq | ❓ | #adb5bd | Frequently asked questions |

### 6.2 Internationalization
- 11 locales: en, fr, de, lb, nl, es, it, pt, hu, ro, pl
- 686 translation keys per language
- Browser language detection + user preference override
- French, German, Luxembourgish have full translations for core UI
- Other languages have English fallbacks (translatable by community)

### 6.3 Authentication & Authorization
- 6 roles: bureau_master, bureau_member, instructor, assistant, member, pending
- Email verification required for all member features
- Unverified users cannot post content (classifieds, comments)
- Admin impersonation with visible banner

### 6.4 CAPTCHA (Registration)
- Honeypot field: hidden `website` input, bots fill it → rejected silently
- Timestamp check: form must take >3 seconds to submit
- No external dependencies, invisible to real users

### 6.5 Content Sanitization
- All user-generated HTML (articles, comments, classifieds) processed through HTMLPurifier
- Comments limited to: p, br, strong, b, em, i, a[href]
- Articles allow full HTML subset

### 6.6 Scheduled Tasks
| Schedule | Task |
|----------|------|
| Daily 08:00 | Medical certificate expiry reminders (30/15/7/0 days) |
| Every minute | Vote auto-open/close |
| Sunday 03:00 | Weekly database backup (last 4 retained) |
