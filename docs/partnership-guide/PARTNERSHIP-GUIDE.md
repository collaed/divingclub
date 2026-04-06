# 🤝 Inter-Club Partnership Guide

Two DivingClub-Manager instances can establish a trust relationship, allowing members from one club to register for events at the other. This guide walks through the complete setup using two real instances:

- **Club A**: Club Européen de Plongée (CEP) — `test.clubcep.eu`
- **Club B**: Plongée Alsace — `divingclub.ecb.pm`

---

## How It Works

Each club generates API credentials (Key ID + Secret). They exchange these credentials so each side can authenticate API calls from the other. Once paired:

1. Either club can **browse the other's federated events**
2. A partner club can **register its members** for events with external slots
3. The host club **approves or rejects** external registrations
4. The partner member receives an **email notification** of the decision

```
Club A                              Club B
  │                                   │
  │  1. Generate Key ID + Secret      │
  │  2. Share with Club B             │
  │                                   │
  │      Club B generates their own   │
  │      Key ID + Secret, shares back │
  │                                   │
  │  3. Mark event as "Federated"     │
  │     Set external_slots = 4        │
  │                                   │
  │  ◄── 4. GET /api/federation/events│
  │  ──► Returns event list           │
  │                                   │
  │  ◄── 5. POST /api/federation/     │
  │         register (member details) │
  │  ──► Returns registration_id      │
  │                                   │
  │  6. Admin approves/rejects        │
  │  ──► Email sent to member         │
```

---

## Step 1 — Create the Partnership (Club A)

Go to **Admin → Club Partnerships → + Add Partner**.

The system generates a Key ID and Secret automatically. Fill in:
- **Club Name**: the partner club's name
- **Base URL**: their DivingClub-Manager URL

![Add Partner Form](02_cep_create_partnership.png)

> ⚠️ **Copy the Secret now** — it cannot be retrieved later. Share the Key ID and Secret with the partner club via a secure channel.

---

## Step 2 — Create the Partnership (Club B)

The partner club does the same: **Admin → Club Partnerships → + Add Partner**.

They generate their own Key ID + Secret and share them back with Club A.

![Alsace Partnerships](07_alsace_partnerships_list.png)

---

## Step 3 — Exchange Credentials

Each club enters the other's credentials in the **"Outbound Credentials"** section:
- **Their Key ID**: the Key ID the partner shared with you
- **Their Secret**: the Secret the partner shared with you

This enables the "Browse Events" button.

Once both sides have exchanged credentials, the partnerships list shows the partner with a "Browse Events" button and registration count:

![CEP Partnerships List](03_cep_partnership_with_alsace.png)

---

## Step 4 — Mark Events as Federated

When creating or editing an event, the host club sets:
- **Federated**: Yes
- **External slots**: number of places reserved for partner clubs (e.g. 4)

The event then appears in the federation API and shows dive site details, weather forecast, and registration panel:

![Federated Event](05_cep_federated_event.png)

---

## Step 5 — Browse Partner Events

Click **"Browse Events"** on the partnerships page to see the partner's federated events. Each event shows:
- Title, date, location, description
- **External slots** badge (e.g. "1/4 external slots")
- Event type

![Browse Remote Events](08_alsace_remote_cep_events.png)

---

## Step 6 — Register a Member

The partner club registers a member via the API:

```
POST /api/federation/register
Headers:
  X-Club-Key-Id: dc_xxx
  X-Club-Secret: yyy

Body:
{
  "event_id": 704,
  "member_name": "Hans Müller",
  "member_email": "hans@example.com",
  "cert_level": "CMAS 2★",
  "medical_valid_until": "2026-12-31",
  "notes": "Has own equipment"
}
```

Response: `{"registration_id": 1, "status": "pending"}`

---

## Step 7 — Approve or Reject

The host club sees the registration in **Admin → Club Partnerships → External Registrations**:

![External Registrations](04_cep_external_registrations.png)

The table shows:
- Member name and email
- Partner club name
- Event name and date
- Certification level
- Medical certificate validity
- Status (pending) with ✓ approve / ✗ reject buttons

The **Bureau Worklist** on the admin dashboard also shows "External registrations to review: 1":

![Admin Dashboard](06_cep_admin_dashboard.png)

When approved or rejected, an email is automatically sent to the external member.

---

## API Reference

All endpoints require authentication via headers:
- `X-Club-Key-Id`: the Key ID shared by the host club
- `X-Club-Secret`: the Secret shared by the host club

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/federation/events` | List federated events with available slots |
| `POST` | `/api/federation/register` | Register an external member |
| `GET` | `/api/federation/register/{id}` | Check registration status |
| `DELETE` | `/api/federation/register/{id}` | Cancel a registration |

Rate limit: 30 requests per minute per partner.

---

## Security

- Secrets are **hashed** (bcrypt) — never stored in plain text
- Outbound secrets are **encrypted** (AES-256) at rest
- Each partnership can be **deactivated** or **removed** instantly
- API is **rate-limited** (30 req/min)
- Only events explicitly marked as **federated** are visible
- External registrations require **manual approval**

---

*This guide was generated from a live test between test.clubcep.eu and divingclub.ecb.pm on April 6, 2026.*
