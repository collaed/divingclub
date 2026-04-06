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
