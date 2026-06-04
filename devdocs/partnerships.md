## partnerships.md — Inter-Club Federation API

## Overview

Bidirectional partnership system allowing clubs to share events and register external members across instances. Each club runs its own DivingClub-Manager instance; they exchange data via a REST API authenticated with shared API keys.

## Data Model

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `club_partnerships` | Partner club definitions | id, name, base_url, api_key_id, api_secret_hash (bcrypt), their_api_key_id, their_api_secret (encrypted), is_active, last_sync_at |
| `external_registrations` | External member registrations for our events | event_id, partnership_id, external_member_name, external_member_email, external_member_phone, external_member_federation, external_member_licence_no, external_member_emergency_contact, external_member_iban, external_cert_level, external_medical_valid_until, status, notes, external_ref |

## Credential Architecture

Each partnership stores **two** credential sets:

1. **Inbound** (our side, for partner to call us):
   - `api_key_id` — plain text identifier
   - `api_secret_hash` — bcrypt hash of the secret (we never store the raw secret)
   
2. **Outbound** (their side, for us to call them):
   - `their_api_key_id` — plain text
   - `their_api_secret` — encrypted with `Crypt::encryptString()` (reversible, needed for outbound calls)

## Authentication

All API requests carry two headers:
```
X-Club-Key-Id: <key_id>
X-Club-Secret: <raw_secret>
```

Server looks up `club_partnerships` by key_id, then `Hash::check(secret, api_secret_hash)`.

## API Endpoints (Inbound — `FederationApiController`)

| Method | Route | Purpose |
|--------|-------|---------|
| `GET` | `/api/federation/events` | List federated events with slot availability |
| `POST` | `/api/federation/register` | Register external member for an event |
| `DELETE` | `/api/federation/register/{id}` | Cancel a registration |
| `GET` | `/api/federation/register/{id}` | Check registration status |

### GET /api/federation/events Response
```json
{
  "events": [{
    "id": 42,
    "title": "Quarry Dive Remerschen",
    "event_date": "2026-07-15",
    "event_time": "09:00",
    "location": "Remerschen quarry",
    "event_type": "quarry",
    "external_slots": 5,
    "slots_taken": 2,
    "estimated_cost": 25.00
  }]
}
```

Only returns events where `is_federated = true`, `status = 'published'`, `event_date >= today`.

### POST /api/federation/register
Creates an `ExternalRegistration` with `status = 'pending'`. Checks slot availability (`external_slots - slots_taken > 0`). Returns 409 if no slots.

### Registration Status Flow
```
pending → approved (by host club bureau)
pending → rejected (by host club bureau)
pending → cancelled (by sending club via DELETE)
```

## Admin UI (`Admin\PartnershipController`)

| Method | Route | Purpose |
|--------|-------|---------|
| `index` | `GET /admin/partnerships` | List partners with registration counts |
| `create` | `GET /admin/partnerships/create` | Form with auto-generated key pair |
| `store` | `POST /admin/partnerships` | Save partnership credentials |
| `destroy` | `DELETE /admin/partnerships/{id}` | Remove partnership |
| `remoteEvents` | `GET /admin/partnerships/{id}/events` | Fetch and display partner's federated events |
| `registrations` | `GET /admin/partnerships/registrations` | List all external registrations |
| `approveRegistration` | `POST /admin/registrations/{id}/approve` | Approve + email notification |
| `rejectRegistration` | `POST /admin/registrations/{id}/reject` | Reject + email notification |

## Email Notifications

When bureau approves/rejects an external registration, the external member gets an email (if email provided) with event details and status.

## Event Federation Fields

On `events` table:
- `is_federated` (bool) — whether event is visible to partner clubs
- `external_slots` (int) — how many spots reserved for external members (0 = unlimited)

## Security

- Inbound secrets are bcrypt-hashed (irreversible)
- Outbound secrets are Laravel-encrypted (reversible, needed for HTTP calls)
- Key pair generated via `ClubPartnership::generateKeyPair()` at creation time
- `is_active` flag allows disabling a partnership without deleting
- All requests timeout at 10 seconds
