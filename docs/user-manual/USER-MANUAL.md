# 🤿 DivingClub-Manager — User Manual

*Illustrated walkthrough of the complete club management workflow.*
*Based on a live demo with Club Européen de Plongée (CEP), Luxembourg.*

---

## Chapter 1: Season Setup

Before events can be generated, the bureau sets up the season with dates, school holidays, and recurring training patterns.

### 1.1 Admin Settings

Navigate to **Admin → Settings** to configure the club identity, federations, medical rules, and theme.

![Admin Settings](ch01_01_admin_settings.png)

### 1.2 Create a Season

Go to **Admin → Seasons**. Create the season with start/end dates matching the school year (September 15 → July 15 for Luxembourg).

![Seasons List](ch01_02_seasons_list.png)

**Add school holidays** so events are not generated during breaks:
- Toussaint (1-9 Nov), Noël (20 Dec - 4 Jan), Carnaval (14-22 Feb)
- Pâques (28 Mar - 12 Apr), Ascension (14 May), Pentecôte (23-31 May)
- Fête nationale (23 Jun)

**Add recurring patterns** — the weekly training schedule:

| Day | Time | Activity | Location | Max |
|-----|------|----------|----------|-----|
| Monday | 19:00-21:00 | Pool | Piscine Steinfort | 16 |
| Wednesday | 17:20-20:00 | Pool | Forum Geesseknäppchen | 20 |
| Friday | 18:30-20:00 | Apnea | Forum Geesseknäppchen | 12 |

Plus one **Fosse** (deep pool) session per month on Thursday or Friday at Nemo 33, Brussels.

---

## Chapter 2: Calendar

Once the season is configured, events are generated automatically from the patterns, skipping holiday weeks.

### 2.1 April 2026

After Easter holidays end (April 12), the regular schedule resumes with pool sessions on Monday, Wednesday, and Friday.

![Calendar April](ch02_01_calendar_april.png)

### 2.2 May 2026

Pentecost holidays (May 23-31) create a gap in the schedule. The Fosse session on May 15 is visible.

![Calendar May](ch02_02_calendar_may.png)

### 2.3 June 2026

The season winds down toward July 15. Fête nationale (June 23) is marked.

![Calendar June](ch02_03_calendar_june.png)

---

## Chapter 3: Instructor Availability

The instructor calendar has a distinct **teal theme** to differentiate it from the regular event calendar. It shows which instructors are available for each session.

### 3.1 Marking Availability

Instructors click **➕** on an event to mark themselves available. Their colored initial appears on the event. Click **✅** to remove.

Regular members see this calendar in **read-only mode** — they can check who's teaching but cannot modify it.

![Instructor Calendar April](ch03_01_instructor_april.png)

The **Activity Types** legend at the bottom shows the color coding: Pool (blue), Apnea (green), Fosse (olive), Quarry (magenta), etc.

### 3.2 Cancellation Warning

Events without any instructor availability **must be cancelled 2 hours before**. The bureau monitors the calendar and contacts instructors when gaps appear.

![Instructor Calendar May](ch03_02_instructor_may.png)

> ⚠️ Events with no instructor initials need attention. The bureau should send a reminder or cancel the session.

---

## Chapter 4: Event Registration

Members register for events from the event detail page. The page shows all relevant information: date, time, location, dive site details, weather forecast, and participant list.

### 4.1 Event Detail

![Event Detail](ch04_01_event_detail.png)

The right sidebar shows:
- **Registration panel** — register yourself or another member (bureau proxy)
- **Participants list** — who's registered, with waiting list positions
- **Dive Groups** — buddy pair planning (for dive events)

When the event reaches maximum capacity, new registrations go to the **waiting list** and are automatically promoted when someone cancels.

---

## Chapter 5: Payments & Reconciliation

Paid events (trips, Fosse, social events) generate payment records with unique communication codes for bank transfers.

### 5.1 Payment Overview

![Payments List](ch05_01_payments_list.png)

The summary cards show:
- **Collected** — total amount received
- **Outstanding** — total still owed
- **Paid / Pending** — count of records by status

Each payment has a unique **communication code** (e.g. `CEP-VTXC-14`) that members include in their bank transfer. This enables automatic matching during reconciliation.

### 5.2 Pending Payments

Filter by status to see only outstanding payments:

![Pending Payments](ch05_02_payments_pending.png)

The **Bank Reconciliation** button at the bottom opens the assisted matching tool — upload a bank statement CSV and the system fuzzy-matches transactions to expected payments.

---

## Chapter 6: Inter-Club Partnerships

Two DivingClub-Manager instances can establish a trust, allowing members from one club to register for events at the other.

### 6.1 Partnership Setup

![Partnerships](ch06_01_partnerships.png)

Each club generates API credentials (Key ID + Secret) and shares them with the partner. The "Browse Events" button shows the partner's federated events.

### 6.2 External Registrations

Partner clubs register their members via API. The host club approves or rejects from the **External Registrations** page:

![External Registrations](ch06_02_external_regs.png)

Each registration shows the member's name, email, certification level, medical certificate validity, and status. Approved members receive an email confirmation.

---

## Chapter 7: Event Communications

The email system allows the bureau to send targeted messages to member groups or event participants.

### 7.1 Email Templates & Sending

![Email System](ch07_01_email_system.png)

- **Templates** — reusable email templates with variables (`{{first_name}}`, `{{club_name}}`, etc.)
- **Send Email** — select a template and target group (all members, instructors, bureau, etc.)
- **Email Log** — full history of all sent emails with status (sent/failed)

Event reminder emails appear in the log with the event name and recipient. All emails are load-balanced across 3 providers (Resend × 2 + Mailjet).

---

## Chapter 8: Dive Group Planning

For dive events, the group planner organizes participants into buddy pairs (palanquées) following FFESSM safety rules.

### 8.1 Dive Groups

![Dive Groups](ch08_01_event_with_groups.png)

Each group has:
- A **leader** (minimum N3 or E1 for deep dives)
- **Divers** paired by certification level
- Planned depth, duration, gas mix, and entry time

The planner validates groups against 14 configurable rules (max depth per level, group size, leader requirements).

---

## Chapter 9: Admin Dashboard

The bureau dashboard provides a complete overview of club operations.

### 9.1 Statistics & Worklist

![Admin Dashboard](ch09_01_dashboard.png)

Key sections:
- **Statistics** — total members, events, attendance, revenue
- **Bureau Worklist** — pending actions (unverified certs, expired medical, missing IBAN, external registrations, upcoming birthdays)
- **Email Sending Quota** — live usage across Resend (×2) + Mailjet with progress bars
- **System** — version, last commit, update check
- **Scheduled Tasks** — heartbeat monitoring for all background jobs

---

## Chapter 10: Newsletter

Rich HTML newsletters with themed templates, article slots, and decorative elements.

### 10.1 Newsletter List

![Newsletters](ch10_01_newsletters_list.png)

### 10.2 Composing a Newsletter

![Newsletter Composer](ch10_02_newsletter_compose.png)

The composer provides:
- **5 article slots** — drag articles from the picker into slots
- **Editable teaser** — customize the preview text per slot
- **Custom URL** — link to external pages (Joomla, static HTML)
- **Decorations** — 25 SVG elements, scatter randomly
- **Preview / Test Send** — see the email rendering before sending

---

## Chapter 11: Email Delivery Stats

Track email delivery across all providers with per-recipient status.

### 11.1 Delivery Dashboard

![Email Stats](ch11_01_email_stats.png)

Navigate by date to see:
- **Summary cards** — total messages, opened, clicked, failed
- **Per-subject breakdown** — each newsletter/email grouped separately
- **Per-recipient status** — ✓ Clicked (green), 👁 Opened (green), 📤 Sent (yellow), ✗ Failed (red)

Data is pulled live from Mailjet API and both Resend API keys.

---

## Chapter 12: Member Dashboard

The modern tile dashboard provides quick access to the most-used features, adapted to the user's role.

### 12.1 Quick Actions

![Tile Dashboard](ch12_01_tile_dashboard.png)

**All members** see: Events, My Profile, Instructor Calendar, Documents, Payments, Classifieds.

**Bureau members** additionally see (yellow tiles): Worklist (with badge count), Members, Dive Sites, Equipment, Send Email, Newsletters, Reconciliation, Email Stats.

Below the tiles:
- **My Upcoming Dives** — events the member is registered for
- **Recent Articles** — latest published articles with thumbnail teasers

---

*Screenshots captured from test.clubcep.eu on April 6, 2026.*
*Generated by DivingClub-Manager v1.1.0.*


---

## Chapter 13: Member Management

### 13.1 Member List

Navigate to **Admin → Members** to see all club members with search, role badges, and status.

![Member List](ch13_01_members_list.png)

### 13.2 Member Profile

Click a member to view their full profile: personal details, certifications, federation licences, medical certificates, and documents.

![Member Profile](ch13_02_member_profile.png)

Bureau members can edit any field, verify certificates, and assign roles.

### 13.3 My Profile

Members edit their own profile at **My Account → Profile**: emergency contacts, phone, address, avatar, and certification uploads.

![My Profile](ch13_03_my_profile.png)

---

## Chapter 14: Medical Compliance

### 14.1 Rules & Reminders

Medical compliance rules are configured per federation and age bracket in **Admin → Settings**. The system automatically sends reminders at 30, 15, 7, and 0 days before expiry.

![Medical Rules](ch14_01_medical_rules.png)

Members with expired medical certificates are **blocked from registering** for dive events. The bureau worklist shows "Active members without medical cert" as a pending action.

---

## Chapter 15: Equipment

### 15.1 Inventory

**Admin → Equipment** shows all club equipment with short numbers, type, condition, status, and current loan.

![Equipment List](ch15_01_equipment_list.png)

### 15.2 Adding Equipment

Click **+ Add** to register new equipment: type, brand, serial number, purchase date, condition, and maintenance schedule.

![Add Equipment](ch15_02_equipment_create.png)

**Quick-loan**: select a member and equipment type — the system suggests available items matching the member's BCD size preference.

**Maintenance**: rules define intervals per equipment type (e.g. "Tank retest every 24 months"). The system auto-schedules the next maintenance date.

---

## Chapter 16: Articles & CMS

### 16.1 Article List

**Admin → Articles** shows all articles with type badges, publication status, and cross-language search.

![Articles List](ch16_01_articles_list.png)

13 article types: news, event report, training, safety, trip report, history, values, bureau, instructors, member figures, schedule, contact, custom.

### 16.2 Creating an Article

The editor uses TinyMCE for rich text, image galleries, and embedded videos. Articles can be auto-translated to all 15 languages.

![Create Article](ch16_02_article_create.png)

### 16.3 Classifieds

Members post classified ads (gear for sale, buddy requests) with 30-day auto-expiry.

![Classifieds](ch16_03_classifieds.png)

---

## Chapter 17: Voting

### 17.1 Vote List

**Admin → Votes** shows all polls and elections with status (draft, open, closed).

![Votes List](ch17_01_votes_list.png)

### 17.2 Creating a Vote

Two modes:
- **Simple** — members can change their vote, results visible immediately
- **Election** — anonymous, irreversible, token-based, results only after closing

![Create Vote](ch17_02_vote_create.png)

Options: multi-select, minimum vote percentage, auto-open/close at scheduled times, embeddable in trip proposals.

---

## Chapter 18: Authentication & Impersonation

### 18.1 Login Options

Members can log in with email/password, or via social providers (Google, Microsoft, Facebook, X) and EU Login (CAS). Failed login attempts are tracked and accounts lock after 5 failures.

### 18.2 Impersonation

Bureau members can impersonate any member to troubleshoot their experience. Click the impersonate icon next to a member's name in the member list.

![Impersonation](ch18_01_impersonation.png)

---

## Chapter 19: Theme & Homepage

### 19.1 Theme Settings

**Admin → Settings → Theme** offers 6 presets (Ocean, Coral, Lagoon, Abyss, Tropical, Arctic) plus custom colors, logo upload, and dark mode.

![Theme Settings](ch19_01_theme_settings.png)

### 19.2 Homepage Widget Editor

The classic homepage uses a drag-and-drop widget layout. Click **⚙ Edit Layout** to rearrange widgets, set visibility (public/members/instructors/bureau), and configure each widget.

![Homepage Widgets](ch19_02_homepage_widgets.png)

---

## Chapter 20: Backup

**Admin → Backups** provides a full backup system: create, inspect, download, and delete backups. Each backup includes the database dump and all uploaded files with a JSON manifest.

Weekly automatic backups run on Sunday at 03:00, retaining the last 4.

---

## Chapter 21: Documents

### 21.1 Bureau Library

**Admin → Documents** is the club file manager: drag-drop upload, folder organization, search, bulk ZIP download, and inline PDF/image preview.

![Document Library](ch21_01_library.png)

### 21.2 Member Documents

Members browse club documents and their own files (medical certs, certifications) at **Resources → Documents**.

![Member Documents](ch21_02_member_documents.png)

---

## Chapter 22: Free Trial

### 22.1 Public Request Form

Visitors can request a trial dive session from the public page. The form includes a honeypot CAPTCHA to prevent spam.

![Trial Page](ch22_01_trial_page.png)

### 22.2 Admin Management

Bureau members manage trial requests: confirm, schedule, or reject.

![Trial Admin](ch22_02_trial_admin.png)

---

## Chapter 23: GDPR

**Admin → GDPR** manages consent records, data export (JSON), and right-to-erasure with anonymization.

![GDPR](ch23_01_gdpr.png)

Members can export their personal data or request account erasure from **My Account**.

---

## Chapter 24: Languages

The site supports 15 locales. Members select their preferred language from the dropdown in the navigation bar. The preference is saved for future visits.

![Language Selector](ch24_01_language.png)

Articles are auto-translated and displayed in the member's preferred language via tabbed reader UI.

---

## Chapter 25: Dive Sites

**Admin → Dive Sites** manages the dive site database: depth, conditions, marine life, safety notes, access, facilities, nearest hospital, and weather integration.

![Dive Sites](ch25_01_dive_sites.png)

Each dive site links to Google Maps and shows a live weather forecast (Open-Meteo) on the event page.

---

## Chapter 26: Admin Guide

A 24-page in-app guide accessible at **Admin → Guide** covers all setup and operational procedures.

![Admin Guide](ch26_01_admin_guide.png)

---

## Chapter 27: License Management

### 27.1 Free Tier

The system works for free up to **100 members**. Beyond that, a license key is required. Without a valid key:
- New member registrations are blocked
- An "Unlicensed" warning appears on the dashboard
- PDF exports carry a watermark

### 27.2 Checking License Status

```bash
php artisan tinker --execute "
echo 'Members: ' . App\Models\User::count();
echo ' | Needs license: ' . (App\Services\LicenseService::needsLicense() ? 'yes' : 'no');
echo ' | License valid: ' . (App\Services\LicenseService::isValid() ? 'YES ✅' : 'NO ❌');
"
```

### 27.3 Installing a License Key

1. Go to **Admin → Settings → License**
2. Paste the license key (long base64 string with a dot in the middle)
3. Click **Save**
4. The system immediately verifies the RSA signature, domain binding, and expiry

### 27.4 Generating a License Key (maintainer only)

The RSA private key is kept offline by the maintainer. To generate:

```bash
php scripts/generate-license.php scripts/license-private.pem clubcep.eu 500 2027-07-31
```

Parameters: **domain** (must match site URL), **max_members**, **expires** (YYYY-MM-DD, default +13 months).

### 27.5 Renewal

The dashboard shows a warning 30 days before expiry. To renew: the maintainer generates a new key, the club admin pastes it in Settings.

### 27.6 Security

- The private key (`license-private.pem`) must **never** be shared or committed to Git
- Only the public key is embedded in the source code
- If compromised, regenerate the key pair and redistribute all licenses

---

## Chapter 28: Audit Log

**Admin → Audit Log** records all actions with old/new values, user, IP address, and timestamp. Filterable by action, user, and date. Exportable as CSV.

![Audit Log](ch27_01_audit_log.png)

Retention policy: logs older than the configured period are automatically cleaned up monthly.

---

*Complete manual — 27 chapters, 46 screenshots.*
*Screenshots captured from test.clubcep.eu on April 6, 2026.*
