## voting.md — Voting System

## Overview

Two voting modes: **simple** (public, changeable) and **election** (anonymous, irreversible). Token-based access allows embedding votes in trip proposals or sending links via email. Auto-open/close via scheduled job.

## Data Model

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `votes` | Vote definitions | id, title, description, mode (simple/election), allow_multiple, allow_change, num_positions, min_vote_pct, is_public, status, opens_at, closes_at, created_by, deleted_at |
| `vote_options` | Choices for a vote | vote_id, label, sort_order |
| `vote_tokens` | Per-user access tokens | vote_id, user_id, token (unique 128-char), is_consumed, consumed_at |
| `vote_ballots` | Cast votes | vote_id, vote_option_id, token_hash (SHA-256 of token, or null for elections) |

## Modes

### Simple Mode
- `token_hash` stored on ballots — links ballot to voter (non-anonymous)
- `allow_change = true` → voter can update their choice until closes_at
- `allow_multiple = true` → voter can select multiple options
- Live results visible to bureau

### Election Mode
- `token_hash = null` on ballots — completely anonymous, no link back to voter
- `is_consumed` flag on token prevents re-voting (irreversible)
- `num_positions` defines how many candidates a voter can select (e.g. elect 3 from 8)
- Once consumed, cannot be changed

## Status Flow

```
draft → open → closed
```

Transitions:
- `draft` → `open`: manual or auto via `opens_at` timestamp
- `open` → `closed`: manual or auto via `closes_at` timestamp

## Controllers

### `Admin\VoteController` (Bureau)

| Method | Route | Purpose |
|--------|-------|---------|
| `index` | `GET /admin/votes` | List all votes with stats |
| `create` | `GET /admin/votes/create` | Create form |
| `store` | `POST /admin/votes` | Save vote + options |
| `show` | `GET /admin/votes/{id}` | Results view |
| `edit` | `GET /admin/votes/{id}/edit` | Edit (if not yet open) |
| `update` | `PUT /admin/votes/{id}` | Update vote |
| `destroy` | `DELETE /admin/votes/{id}` | Soft delete |
| `open` | `POST /admin/votes/{id}/open` | Manually open |
| `close` | `POST /admin/votes/{id}/close` | Manually close |

### `VotePublicController` (Token-based, no auth required)

| Method | Route | Purpose |
|--------|-------|---------|
| `show` | `GET /vote/{token}` | Display ballot form |
| `cast` | `POST /vote/{token}` | Submit vote |

## Scheduled Job: `AutoOpenCloseVotes`

Runs **every minute**. Opens votes where `opens_at <= now()` and status is `draft`. Closes votes where `closes_at <= now()` and status is `open`.

## Article Integration

Articles have a nullable `vote_id` FK. Trip proposals (`article_type = 'trip_proposal'`) can embed a vote directly in the article view, allowing members to vote on trip destinations inline.

## Token Generation

Tokens are generated when a vote is created/opened. Each eligible member gets a unique 128-character random token. The token URL (`/vote/{token}`) is sent via email or linked from the article.

## Validation

- `StoreVoteRequest`: title required, mode in [simple, election], options array min 2, opens_at/closes_at as dates
- Election: num_positions required, min 1, max options count
- Simple: allow_change, allow_multiple as booleans
