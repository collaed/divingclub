# 🤿 DivingClub-Manager — Complete Testing Guide

*Optimized walkthrough that tests 100% of features with minimum repetitive actions.*
*Estimated time: 90 minutes for a thorough test, 45 minutes for a quick pass.*

---

## Phase 0: Fresh Install (5 min)

```bash
git clone https://github.com/collaed/divingclub.git
cd divingclub
composer install && npm ci && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve --port=8080
```

Open http://localhost:8080 → you should see the **install wizard**.

### Install Wizard
- [ ] Club name, admin email, password → Submit
- [ ] Redirected to login page
- [ ] Login with admin credentials → Dashboard loads

---

## Phase 1: Admin Setup (10 min)

*Stay logged in as admin throughout this phase.*

### 1.1 Settings (Admin → Settings)
- [ ] Set club name, address, IBAN, email
- [ ] Add training locations (Steinfort, Geesseknäppchen)
- [ ] Set theme preset (Ocean) → colors change
- [ ] Toggle dark mode → reverts
- [ ] Set federation visibility: FFESSM=active, FLASSA=active, others=recognized
- [ ] Add medical rules: FFESSM all ages 12mo, FLASSA <40 12mo, FLASSA 40+ 12mo

### 1.2 Season (Admin → Seasons)
- [ ] Create season 2025-2026 (Sep 15 → Jul 15)
- [ ] Add holidays (Toussaint, Noël, Carnaval, Pâques, Pentecôte)
- [ ] Add patterns: Mon pool, Wed pool, Fri apnea
- [ ] Generate events → calendar fills up
- [ ] Verify holidays have no events

### 1.3 Seed Data
```bash
php artisan db:seed --class=SampleDataSeeder
```
- [ ] Members list shows ~20 sample members
- [ ] Equipment list shows items
- [ ] Dive sites populated

### 1.4 Roles & Permissions (Admin → Roles & Permissions)
- [ ] Matrix shows 8 roles × 20 permissions
- [ ] Check a permission on instructor → Save → verify it sticks
- [ ] Uncheck it → Save → verify removed

### 1.5 Quick Checks
- [ ] Admin Dashboard: stats cards, worklist, mail quota, scheduled tasks with frequencies
- [ ] Admin Guide: 24 sections load
- [ ] Audit Log: shows recent actions

---

## Phase 2: Public Visitor (10 min)

*Open a private/incognito browser window — no login.*

### 2.1 Public Pages
- [ ] `/home3` — hero cycles photos, numbers animate on scroll
- [ ] Click Login button → slide-in panel opens
- [ ] Press Escape → panel closes
- [ ] Photo mosaic → click photo → fullscreen gallery, ← → arrows, Escape closes
- [ ] Events section shows 4 different activities
- [ ] Footer links work

### 2.2 Trial Dive Request
- [ ] `/trial` — fill form: name, email (`trial-tester@example.com`), phone, preferred date
- [ ] Submit → success message
- [ ] *(Back in admin window)* Admin → Trials → request appears → Confirm it

### 2.3 Registration
- [ ] `/register` — create account: `newdiver@example.com`, password, first/last name
- [ ] Email verification (check log driver or mailpit)
- [ ] After verification → redirected to profile
- [ ] Profile completion banner shows missing fields

### 2.4 Language
- [ ] Switch language to French → UI changes
- [ ] Switch to German → UI changes
- [ ] Switch back to English → preference saved

---

## Phase 3: New Member Journey (15 min)

*Stay logged in as `newdiver@example.com` from Phase 2.*

### 3.1 Complete Profile
- [ ] Profile → Info tab: fill name, nationality, sex, phone → Save → stays on Info tab
- [ ] Private tab: fill DOB, address, country, emergency contact → Save → stays on Private tab
- [ ] Upload avatar → appears in header
- [ ] Diving tab: set cert level, dive count → Save

### 3.2 Upload Medical Certificate
- [ ] Medical tab → Upload: select PDF, category=medical, leave date empty
- [ ] Submit → stays on Medical tab
- [ ] OCR job runs (check Horizon) → date_established auto-detected
- [ ] File renamed to `lastname-firstname-medical-date.pdf`
- [ ] Compliance notes show federation rules evaluated

### 3.3 Browse Events
- [ ] Calendar → month view shows events
- [ ] Click a pool event → detail page with location, weather forecast
- [ ] Register → status changes to "Registered"
- [ ] Register for a paid event (Fosse) → payment record created
- [ ] Try registering for a full event → waiting list position shown

### 3.4 Instructor Calendar
- [ ] `/availability` → teal header, read-only notice
- [ ] Can see instructor initials on events
- [ ] Cannot click ➕ (no toggle buttons visible)
- [ ] Activity type legend at bottom

### 3.5 Documents & Articles
- [ ] Resources → Documents → browse club files
- [ ] "My Documents" section shows uploaded medical cert
- [ ] Read an article → translated tabs if available
- [ ] Post a comment on an article
- [ ] Create a classified ad (gear for sale)

### 3.6 Other Member Features
- [ ] Dive sites → map links, conditions
- [ ] Membership fees calculator
- [ ] GDPR → export personal data (JSON download)
- [ ] Install PWA prompt (if on mobile/Chrome)

---

## Phase 4: Instructor Actions (10 min)

*Back in admin window. Impersonate an instructor (Admin → Members → 🎭 on an instructor).*

### 4.1 Instructor Calendar
- [ ] `/availability` → ➕ buttons visible on events
- [ ] Click ➕ on a pool event → initial appears
- [ ] Click ✅ → initial removed
- [ ] Mark available on 3-4 events

### 4.2 Event Management
- [ ] Open an event you're responsible for
- [ ] See participant list with cert levels and medical status
- [ ] "Register another person" dropdown → proxy register a member
- [ ] View a participant's profile → sees cert, medical, emergency (tierManifest)
- [ ] View a NON-participant's profile → only sees basic info (no medical/emergency)

### 4.3 Dive Group Planning
- [ ] On a dive event → "Open Group Planner"
- [ ] Create Palanquée 1: add leader (N3+), add 2 divers
- [ ] Create Palanquée 2: set depth, duration, gas mix
- [ ] Validation: try adding too many divers → rule violation shown

### 4.4 Stop Impersonation
- [ ] Click "Stop Impersonating" → back to admin

---

## Phase 5: Bureau Operations (15 min)

*Still logged in as admin.*

### 5.1 Member Management
- [ ] Admin → Members → search "Kr" → both KRAEMERs appear (case-insensitive)
- [ ] Instant JS filter: type in search → rows filter without page reload
- [ ] Click a member → edit Private tab → change address → Save → stays on Private tab
- [ ] Renewal tab → edit FLASSA licence number → Save → stays on Renewal tab
- [ ] Verify a medical cert → badge changes to "Compliant"

### 5.2 Worklist Browse
- [ ] Dashboard → "Members pending FLASSA enrolment" → opens first member profile
- [ ] ← Prev / 1/N / Next → navigation works
- [ ] Browse through 3-4 members without going back to dashboard

### 5.3 Payments
- [ ] Admin → Payments → summary cards show totals
- [ ] Filter by "pending" → only unpaid shown
- [ ] Search a member name → instant filter
- [ ] Bank Reconciliation → upload CSV → fuzzy matching

### 5.4 Email
- [ ] Admin → Email → create template with `{{first_name}}` variable
- [ ] Send to "Bureau" group → check email log
- [ ] Email Stats → shows delivery status per recipient

### 5.5 Newsletter
- [ ] Admin → Newsletters → Create
- [ ] Add articles to 5 slots, edit teasers
- [ ] Preview Email → HTML renders correctly
- [ ] Send Test → check your inbox
- [ ] Scatter decorations → random SVG elements appear

### 5.6 Equipment
- [ ] Admin → Equipment → search works (instant + server)
- [ ] Add new equipment item
- [ ] Quick-loan to a member
- [ ] Return the loan

### 5.7 Votes
- [ ] Admin → Votes → Create simple vote (poll)
- [ ] Add 3 options → Save
- [ ] Open the vote → members can vote
- [ ] *(In member window)* Vote on it → result updates
- [ ] Close the vote → results shown

### 5.8 Articles
- [ ] Admin → Articles → Create news article with TinyMCE
- [ ] Add featured image
- [ ] Publish → appears on homepage
- [ ] Trigger auto-translation (if Google Translate API configured)

---

## Phase 6: Inter-Club Partnership (5 min)

### 6.1 Setup
- [ ] Admin → Partnerships → + Add Partner
- [ ] Copy Key ID + Secret
- [ ] *(On second instance or via API)* Create reciprocal partnership
- [ ] Exchange credentials

### 6.2 Federation
- [ ] Browse partner events → federated events visible with slot count
- [ ] Register external member via API → appears in External Registrations
- [ ] Approve → email sent to external member
- [ ] Reject another → status changes

---

## Phase 7: Financial Audit (5 min)

*Impersonate an auditor (Frédérique VIGNERON).*

### 7.1 Auditor View
- [ ] Admin menu shows only "Financial Audit" and "Audit Log"
- [ ] Financial Audit → summary cards, payment table, bank transactions
- [ ] All read-only — no edit/delete buttons
- [ ] Can browse pages of payments

### 7.2 Stop Impersonation → back to admin

---

## Phase 8: Edge Cases & Security (10 min)

### 8.1 Privacy
- [ ] As regular member: view another member's profile → no email, phone, address visible
- [ ] As instructor: view non-participant → only basic info
- [ ] As instructor: view participant on your event → sees medical, emergency
- [ ] Direct URL to `/admin/dashboard` as member → 403

### 8.2 Input Validation
- [ ] Register with invalid email → error
- [ ] Upload file > 10MB → rejected
- [ ] XSS in article comment: `<script>alert(1)</script>` → escaped, not executed
- [ ] SQL injection in search: `'; DROP TABLE users; --` → no effect

### 8.3 Concurrent Access
- [ ] Two browsers: both register for same event with 1 slot left → one gets waiting list
- [ ] Edit same member profile in two tabs → last save wins, no crash

### 8.4 Mobile
- [ ] Open `/home3` on phone → responsive, login panel works
- [ ] Open `/home4` on phone → tiles stack 2-column
- [ ] Instructor calendar → horizontal scroll, sticky week column

---

## Phase 9: System & Backup (5 min)

### 9.1 Backup
- [ ] Admin → Backups → Create Backup
- [ ] Download → tar.gz contains DB dump + files + manifest
- [ ] Offsite upload → check SFTP server for `dcms-bkp-{domain}-{date}.tar.gz`
- [ ] Delete old backup → removed from list

### 9.2 Dashboard Monitoring
- [ ] Scheduled Tasks → all show frequency + last run + OK/Overdue
- [ ] Email Sending Quota → 3 providers with progress bars
- [ ] System → version, commit, update check
- [ ] Worklist → all pending items with counts

### 9.3 Homepage Variants
- [ ] `/` → classic widget dashboard
- [ ] `/home2` → single-page scroller
- [ ] `/home3` → visual landing (public)
- [ ] `/home4` → tile dashboard (logged in)
- [ ] All 4 load without errors

---

## Quick Reference: Login Sequence

The optimal path minimizes login/logout:

```
1. Admin browser     → stays admin throughout (Phases 1, 5, 6, 9)
2. Incognito browser → visitor → registers → becomes member (Phases 2, 3)
3. Admin impersonates instructor (Phase 4) → stops → back to admin
4. Admin impersonates auditor (Phase 7) → stops → back to admin
5. Security tests use both browsers (Phase 8)
```

**Total logins: 2** (admin + new member). Everything else uses impersonation.

---

## Automated Test Coverage

```bash
# PHPUnit backend tests (233 tests, 532 assertions)
php artisan test --compact

# Playwright E2E — standard suite (35 tests)
python3 -m pytest tests/e2e/test_ui.py -v

# Playwright E2E — adversarial suite (49 tests)
python3 -m pytest tests/e2e/test_adversarial.py -v

# All E2E tests
python3 -m pytest tests/e2e/ -v
```

---

*This guide covers all 28 feature areas documented in the User Manual.*
*Last updated: April 8, 2026.*
