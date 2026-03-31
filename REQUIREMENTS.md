# DivingClub-Manager — Requirements & Status

Consolidated from all sources: original REQUIREMENTS.md (v1: initial commit, v2: session 2 additions), README.md, git history (86 commits, Mar 18–21 2026), tester feedback, and current codebase state.

---

## Project Timeline

| Date | Phase | Key Activity |
|------|-------|-------------|
| Mar 18 AM | Session 1 | Initial commit: full app with 420-line requirements spec |
| Mar 18 AM–PM | Session 1 | 20 feature commits in one day (dive sites → federation API) |
| Mar 18 PM | Session 2 | Requirements v2: added §7 (trial, multi-club, license, instructor cal, translations, branding, safety docs, newsletter/video) |
| Mar 19 | Session 3 | Worklist, audit UI, parental consent, style presets, registration audit, photo system, payment QR |
| Mar 20 AM | Session 4 | Homepage widgets, fiche de sécurité, UDDF/DAN export, documentation (guides, user journeys) |
| Mar 20 PM | Session 5 | Staging deployment (Wasmer Edge), CEP seeder, staging mode, backup system |
| Mar 20 EVE | Session 5 | 15 Wasmer deployment fixes (Shipit, WASI polyfills, storage dirs, sessions) |
| Mar 21 AM | Session 6 | Migrated to Hetzner VPS, PostgreSQL compat fixes, mail aliases, security hardening |
| Mar 21 PM | Session 7 | Tester feedback (13 issues), document upload bug, widget config, this doc |

---

## Requirements Origin Analysis

### Collected Before Development (Session 1 — Initial Commit)
The original 420-line REQUIREMENTS.md was a screen-by-screen spec covering 6 sections and ~85 functional requirements. This was the "blueprint" — all of §1–§6 was implemented in the initial commit.

**Estimated: ~65% of total functional requirements were defined upfront.**

### Added During Development (Sessions 2–7)
Features added iteratively, either from the developer's domain knowledge or from tester feedback:

- §7.1–7.9 (trial page, multi-club, license, instructor calendar, translations, branding, homepage optimization, safety docs, newsletter/video) — added in session 2, documented in REQUIREMENTS.md v2
- Dive group planner (Trello-style), buddy board, weather widgets — session 1, not in original spec
- Bureau worklist, parental consent, audit UI — session 3, not in any spec
- UDDF/DAN export, fiche de sécurité — session 4, not in any spec
- Backup system, staging mode, mail aliases — sessions 5–6, operational needs
- Homepage widget system (drag-and-drop, configurable) — session 4, not in any spec
- Per-page selector, sortable headers, zone-adaptive photos — session 7, from tester feedback
- Medical cert email notification, document upload fix — session 7, from tester feedback

**Estimated: ~25% emerged during development (developer domain knowledge), ~10% from tester feedback.**

### Requirements Document Integrity Issue
The REQUIREMENTS.md was overwritten from 486 lines (v2) to 143 lines (current) during commit `2a58503`. The current file contains only code-quality/refactoring recommendations (REQ-01 through REQ-14), not functional requirements. The original functional spec was lost from the file.

---

## Functional Requirements — Complete Status

### §1. Public Screens

| ID | Requirement | Status | Notes |
|----|------------|--------|-------|
| 1.1 | Welcome / Landing page | ✅ Done | Marketing page with club branding |
| 1.2 | About pages (7 pinned articles) | ✅ Done | Schedule, values, contact, history, bureau, member-figures, instructors |
| 1.3 | Home page (article feed) | ✅ Done | Configurable widget layout with drag-and-drop (exceeded spec) |
| 1.4 | Article detail with comments | ✅ Done | Threaded comments, gallery, prev/next, embedded votes |
| 1.5 | Contact page | ✅ Done | |
| 1.6 | Dues calculator with SEPA QR | ✅ Done | |
| 1.7 | PWA offline page | ✅ Done | Service worker + installable |
| — | Try Diving page | ✅ Done | Added session 2 (§7.1), hidden for certified members |
| — | iCal feed (/calendar.ics) | ✅ Done | Added session 2, not in spec |

### §2. Authentication

| ID | Requirement | Status | Notes |
|----|------------|--------|-------|
| 2.1 | Registration with honeypot CAPTCHA | ✅ Done | |
| 2.2 | Login with lockout | ✅ Done | Throttle 5/min added session 6 |
| 2.3 | Forgot password | ✅ Done | Fixed session 5 |
| 2.4 | Reset password | ✅ Done | |
| 2.5 | Email verification | ✅ Done | MustVerifyEmail fixed session 6 |
| — | OAuth (Google, Facebook, Microsoft, X) | 🟡 Partial | Socialite installed, buttons dynamic. Google/X blocked pending DNS/HTTPS |
| — | License system (RSA-signed) | ✅ Done | Added session 2 (§7.3), free tier ≤100 members |

### §3. Member Screens

| ID | Requirement | Status | Notes |
|----|------------|--------|-------|
| 3.1 | Profile (8 tabs) | ✅ Done | Info, private, diving, medical, language, registrations, equipment, renewal |
| 3.2 | Events list (calendar view) | ✅ Done | Month/week/day toggle |
| 3.3 | Event detail + registration | ✅ Done | Medical gate, waiting list, WhatsApp, Google Maps |
| 3.4 | Event create/edit | ✅ Done | Assistant IDs as member dropdown (fixed session 7) |
| 3.5 | Classifieds listing | ✅ Done | 30-day auto-expiry |
| 3.6 | Classified form | ✅ Done | |
| 3.7 | Document browser | ✅ Done | Folder navigation, thumbnails |
| 3.8 | Members directory | ✅ Done | Searchable, sortable (session 7) |
| 3.9 | Trombinoscope | ✅ Done | Photo grid |
| 3.10 | GDPR consents | ✅ Done | Download data + erasure |
| 3.11 | GDPR erasure | ✅ Done | Anonymization |
| 3.12 | Vote page (token-based) | ✅ Done | Simple + election modes |
| — | Dive group planner (Trello-style) | ✅ Done | Not in original spec, added session 1 |
| — | Buddy request board | ✅ Done | Not in original spec |
| — | Instructor availability calendar | ✅ Done | Added session 2 (§7.4), 10 activity types |
| — | FFESSM InfoLicencié QR on profile | ✅ Done | Added session 5 |
| — | Self-service membership status change | ✅ Done | Added session 7 |

### §4. Administration Screens

| ID | Requirement | Status | Notes |
|----|------------|--------|-------|
| 4.1 | Dashboard with charts | ✅ Done | Chart.js, CSV export |
| 4.2 | Members management | ✅ Done | Search, filter, impersonate, medical export CSV+ZIP |
| 4.3 | Articles management | ✅ Done | 13 article types (spec said 11, added newsletter + video) |
| 4.4 | Article form with gallery | ✅ Done | Rich text, image gallery with layout hints |
| 4.5 | Seasons management | ✅ Done | |
| 4.6 | Season detail (patterns, holidays) | ✅ Done | Generate + preview workflow |
| 4.7 | Season form | ✅ Done | |
| 4.8 | Season preview | ✅ Done | |
| 4.9 | Payments list | ✅ Done | |
| 4.10 | Bank reconciliation | ✅ Done | Fuzzy matching, fixed "suggested" key bug (session 7) |
| 4.11 | Payment components | ✅ Done | |
| 4.12 | Equipment inventory | ✅ Done | Sortable columns (session 7) |
| 4.13 | Equipment detail + loans + maintenance | ✅ Done | |
| 4.14 | Equipment form | ✅ Done | |
| 4.15 | Email system (compose, templates, log) | ✅ Done | |
| 4.16 | Votes management | ✅ Done | |
| 4.17 | Vote detail + tokens | ✅ Done | |
| 4.18 | Vote form | ✅ Done | |
| 4.19 | Links management | ✅ Done | |
| 4.20 | Document library (admin) | ✅ Done | Upload, folders, visibility toggle |
| 4.21 | Audit logs | ✅ Done | Filter, purge |
| 4.22 | Annual report | ✅ Done | Printable |
| 4.23 | Settings (7 sections) | ✅ Done | Federations, statuses, fees, medical rules, maintenance rules, theme, club identity |
| 4.24 | Admin guide (in-app) | ✅ Done | 20 pages (spec said 14, expanded) |
| — | Bureau worklist (dashboard) | ✅ Done | Not in original spec |
| — | Backup system (admin UI) | ✅ Done | Not in original spec, added session 5 |
| — | Staging mail viewer | ✅ Done | Not in original spec, operational need |
| — | Homepage layout editor | ✅ Done | Not in original spec, added session 4 |

### §5. Navigation & Layout

| ID | Requirement | Status | Notes |
|----|------------|--------|-------|
| 5.1 | Responsive navbar with theme | ✅ Done | |
| 5.2 | Footer with cookie consent | ✅ Done | |

### §6. Cross-Cutting Concerns

| ID | Requirement | Status | Notes |
|----|------------|--------|-------|
| 6.1 | 11 article types | ✅ Done | Expanded to 13 (added newsletter, video) |
| 6.2 | 11 locales (i18n) | ✅ Done | 686+ translation keys |
| 6.3 | 6 roles + authorization | ✅ Done | |
| 6.4 | Honeypot CAPTCHA | ✅ Done | |
| 6.5 | Content sanitization (HTMLPurifier) | ✅ Done | |
| 6.6 | Scheduled tasks (3) | ✅ Done | Medical reminders, vote auto-open/close, weekly backup |

### §7. Features Added in Session 2

| ID | Requirement | Status | Notes |
|----|------------|--------|-------|
| 7.1 | Free trial page | ✅ Done | |
| 7.2 | Multi-club support | ✅ Done | Dynamic ThemeSetting for all club identity |
| 7.3 | License system (RSA) | ✅ Done | |
| 7.4 | Instructor availability calendar | ✅ Done | 10 activity types, AJAX toggle |
| 7.5 | Article translations (auto) | ✅ Done | Google Translate, tabbed UI, stale refresh |
| 7.6 | Club logo & branding | ✅ Done | Meta generator, discoverability |
| 7.7 | Homepage optimization | ✅ Done | Exceeded: full widget system |
| 7.8 | Safety documents per dive site | ✅ Done | |
| 7.9 | Newsletter & video article types | ✅ Done | YouTube/Vimeo auto-embed |

### Features Not in Any Requirements Doc (Emerged During Development)

| Feature | Session | Origin |
|---------|---------|--------|
| Dive group planner (Trello-style) | 1 | Developer domain knowledge |
| Buddy request board | 1 | Developer domain knowledge |
| Weather widgets on dive sites | 1 | Developer domain knowledge |
| Food options for events | 1 | Developer domain knowledge |
| Warehouse page | 1 | Developer domain knowledge |
| Inter-club federation API | 1 | Developer domain knowledge |
| FFESSM/LIFRAS/BSAC dive group rules | 1 | Developer domain knowledge |
| Bureau worklist | 3 | Developer domain knowledge |
| Parental consent system | 3 | Developer domain knowledge |
| UI style presets + dark mode | 3 | Developer domain knowledge |
| FFESSM fiche de sécurité | 4 | Developer domain knowledge |
| UDDF/DAN dive data export | 4 | Developer domain knowledge |
| Homepage widget system (drag-and-drop) | 4 | Developer domain knowledge |
| Weighted-random photo selection | 5 | Developer domain knowledge |
| Backup system with admin UI | 5 | Operational need |
| Staging mode + mail viewer | 5 | Operational need |
| Mail alias system (Postfix) | 6 | Operational need |
| Per-page selector (30/50/100/All) | 7 | Tester feedback |
| Sortable table headers | 7 | Tester feedback |
| Widget config editor (⚙ button) | 7 | Tester feedback |
| Zone-adaptive photo gallery | 7 | Tester feedback |
| Medical cert upload email notification | 7 | Tester feedback |
| Self-service membership status change | 7 | Tester feedback |

---

## Code Quality Requirements (Current REQUIREMENTS.md — REQ-01 to REQ-14)

These are refactoring/architecture improvements, not functional requirements.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| REQ-01 | Extract Form Request classes | High | 🟡 21 Form Requests created, 6 controllers wired up, 64 inline validations remain |
| REQ-02 | Introduce authorization policies | High | 🟡 Form Request authorize() methods enforce role checks for admin endpoints |
| REQ-03 | Expand test coverage (>80% routes) | High | 🟡 134 tests exist, but many routes untested |
| REQ-04 | Break up fat controllers (<200 lines) | High | ❌ Not started |
| REQ-05 | Remove inline closures from routes | High | ❌ Not started |
| REQ-06 | Extract business logic to services | Medium | 🟡 Some services exist (FeeCalculation, BankReconciliation, Medical, Backup, Theme, License, ArticleTranslation, MailAlias) |
| REQ-07 | Create model factories | Medium | ❌ Only UserFactory exists |
| REQ-08 | Rate limiting on sensitive endpoints | Medium | ✅ Done — 12 throttled routes: login, register, password reset, trial, contact, vote, verification, cron (4) |
| REQ-09 | Secure cron endpoints (no GET secrets) | Medium | ✅ Done — accepts X-Cron-Key header (preferred) or query param (backward compat), rate limited, null-safe |
| REQ-10 | Consistent authorization pattern | Medium | ❌ Not started |
| REQ-11 | API versioning | Low | ❌ Not started |
| REQ-12 | Replace static ThemeSetting with service | Low | ❌ Not started |
| REQ-13 | Scope license watermark view composer | Low | ❌ Not started |
| REQ-14 | Consistent DB transaction usage | Low | ❌ Not started |

---

## Infrastructure & Deployment Status

| Item | Status | Notes |
|------|--------|-------|
| Hetzner VPS (204.168.168.60) | ✅ Running | Ubuntu 24.04, Caddy, PHP 8.3, PostgreSQL 16 |
| UFW firewall + fail2ban | ✅ Configured | 8 rules, SSH key-only |
| Caddy security headers | ✅ Configured | CSP, X-Frame-Options, Permissions-Policy |
| DNS (laravel.clubcep.eu) | ❌ Not propagated | A record needed at Namecheap → 204.168.168.60 |
| HTTPS | ❌ Blocked | Waiting on DNS |
| Google OAuth | ❌ Blocked | Needs HTTPS callback URL on domain |
| X OAuth | ❌ Blocked | Needs callback URL update in X developer portal |
| Postfix mail aliases | ✅ Working | bureau@, members@, instructors@, all@, event-{id}@ |
| Data seeded on staging | ✅ Done | 100 users, 1215 events, 537 photos, 44 equipment, 13 dive sites |
| Wasmer Edge | ✅ Decommissioned | Both apps deleted, config files removed |

---

## Remaining Work

### Blockers (External)
1. DNS propagation for `laravel.clubcep.eu`
2. Switch Caddy to HTTPS once DNS works
3. Update Google OAuth callback to `https://laravel.clubcep.eu/auth/google/callback`
4. Update X OAuth callback in developer portal

### Functional
5. Extend sortable headers to events, articles, payments lists
6. Add `<x-per-page>` component to remaining list views (component exists, controllers ready)
7. Fix unconfigured OAuth provider routes returning 500 (Microsoft route exists but no driver)

### Code Quality (from REQ-01 to REQ-14)
8. Extract Form Request classes (REQ-01)
9. Introduce authorization policies (REQ-02)
10. Expand test coverage (REQ-03)
11. Break up fat controllers (REQ-04)
12. Remove inline route closures (REQ-05)
13. Rate limiting on sensitive endpoints (REQ-08)
14. Secure cron endpoints (REQ-09)

### Nice-to-Have (from competitor analysis)
15. Stripe/card payment integration
16. Push notifications via PWA
17. Email open/click tracking
18. Equipment self-reservation by members

---

## Project Conduct Assessment

**Development approach**: AI-assisted rapid prototyping with a solo developer providing domain expertise. The project went from zero to a 260-route, 63-table, 11-language application in 4 days (Mar 18–21).

**Requirements discipline**:
- ~65% of functional requirements were specified upfront in a detailed screen-by-screen document
- ~25% emerged organically from the developer's deep domain knowledge of diving club operations (FFESSM rules, fiche de sécurité, buddy pairing rules, federation interop)
- ~10% came from real tester feedback in the final session
- The requirements document was accidentally overwritten with a code-quality checklist, losing the functional spec from the file (though it remains in git history)

**What went well**:
- Comprehensive upfront spec enabled rapid implementation
- Domain-specific features (dive group rules, medical compliance, federation interop) add significant value over generic club software
- Quick pivot from Wasmer to Hetzner when deployment platform proved unsuitable
- Tester feedback loop caught real bugs (document upload saving to wrong user, reconciliation crash)

**What could improve**:
- Requirements document should be version-controlled more carefully (the overwrite lost the functional spec)
- Code quality requirements (REQ-01 to REQ-14) are all unstarted — technical debt accumulated during rapid development
- Test coverage is thin relative to the application's complexity (134 tests for 260 routes)
- No formal acceptance criteria per requirement — status tracking was informal


## v1.1.0 Additions

### New Dependencies
- **spatie/laravel-permission** v6 — Role & permission management with granular permissions
- **laravel/horizon** v5 — Redis queue monitoring dashboard
- **intervention/image** v4 — Image manipulation (thumbnails, avatar resize)
- **resend/resend-laravel** v1 — Email delivery via Resend API (replaces SMTP)

### Infrastructure
- **Redis** required on staging/production for Horizon queue processing
- **Supervisor** manages Horizon daemon (auto-restart on crash/reboot)
- **Resend** API for outbound email (SPF/DKIM via Amazon SES infrastructure)

### New Permissions (spatie/laravel-permission)
- `manage members`, `manage events`, `manage equipment`, `manage articles`
- `manage payments`, `manage settings`, `send newsletters`, `manage backups`
- `view audit logs`, `manage dive sites`, `manage votes`, `impersonate users`

### Newsletter System Enhancements
- Per-slot editable teaser text (overrides auto-excerpt)
- Per-slot custom URL (for linking to external pages)
- "EN ›" link in email cards (bottom-left) when English translation exists
- "Send test to me" button for one-click test delivery
- Configurable article base URL in Admin → Settings
- 25 SVG marine decorations with scatter button

### Document Library Enhancements
- Drag-and-drop upload zone
- Search across all folders (file names + descriptions)
- Bulk ZIP download (select files → download as archive)
- Inline image/PDF preview (lightbox overlay)
- "My Documents" section for members showing personal uploads with status

### Translation Quality System
- Source hash tracking (xxh3) for change detection
- Word count validation (30%–300% ratio check)
- Retry logic (max 3 attempts before flagging)
- Auto-flagging for admin review with reason

### Auto-Update System
- GitHub API version check (cached 6h)
- One-click update: git pull → composer → npm → migrate → cache clear
- Bureau Master only, with confirmation dialog
- Version displayed on dashboard with commit info
