## buddy-system.md — Buddy Finder

## Overview

Members post requests to find dive buddies, guides, or dive directors for specific dates and sites. Other members respond with interest. Simple board-style system — no automatic matching algorithm.

## Data Model

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `buddy_requests` | Posted requests | user_id, dive_site_id, location_text, dive_date, dive_time, need_type, description, max_depth, desired_cert_level, max_buddies, is_active |
| `buddy_responses` | Interest replies | buddy_request_id, user_id, message, status |

## Need Types (3)

| Slug | Icon | Label |
|------|------|-------|
| `buddy` | 🤝 | Buddy (equal-level dive partner) |
| `guide` | 👑 | Guide de Palanquée / Divemaster |
| `dp` | 📋 | Directeur de Plongée (dive director) |

## Controller: `BuddyController`

| Method | Route | Purpose |
|--------|-------|---------|
| `index` | `GET /buddies` | List all active requests (future date + is_active) |
| `store` | `POST /buddies` | Create a new buddy request |
| `respond` | `POST /buddies/{buddyRequest}/respond` | Express interest (updateOrCreate per user) |
| `close` | `POST /buddies/{buddyRequest}/close` | Deactivate request (owner or bureau) |

## Active Scope

`BuddyRequest::active()` returns requests where:
- `is_active = true`
- `dive_date >= today()`

Expired requests (past date) automatically drop off the board without cleanup job.

## Validation Rules

- `dive_date`: required, must be today or future
- `need_type`: required, must be in [buddy, guide, dp]
- `dive_site_id`: optional FK to `dive_sites`
- `location_text`: optional free-text alternative to dive_site_id
- `max_depth`: optional, 1–300m
- `desired_cert_level`: optional free-text (e.g. "N2+", "PA40")
- `max_buddies`: optional, 1–10

## Response Behavior

- One response per user per request (updateOrCreate on `buddy_request_id + user_id`)
- Status set to `interested` on create
- Users cannot respond to their own request
- Responders visible to request owner with their cert levels and message

## Relationships

- `BuddyRequest` → belongsTo User, belongsTo DiveSite (nullable), hasMany BuddyResponse
- `BuddyResponse` → belongsTo BuddyRequest, belongsTo User

## View

Single page (`buddies.index`) showing:
- All active requests sorted by dive_date ascending
- Each request card shows: poster's name + cert level, date, site, need_type, depth, description
- Response form inline (message textarea)
- Close button for request owner / bureau
- New request form (selects from active dive sites)
