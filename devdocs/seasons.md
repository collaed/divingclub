## seasons.md — Season & Event Generation

## Overview

Academic-year seasons define recurring weekly event patterns (e.g. "pool every Wednesday 18:30"). Bureau configures patterns and holidays, then bulk-generates events for the entire season. Clone from previous season for quick setup.

## Data Model

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `seasons` | Season definitions | id, year, name, start_date, end_date, is_active |
| `season_patterns` | Recurring event templates | season_id, day_of_week (0=Mon..6=Sun), start_time, end_time, event_type, title, location, description, max_participants, estimated_cost, registration_opens_days_before, registration_closes_days_before, color_hex, whatsapp_group_url, dive_site_id |
| `season_holidays` | Skip periods | season_id, name, start_date, end_date, is_adhoc |

## Active Season

Only one season is active at a time. `activate()` deactivates all others in a transaction.

## Controller: `Admin\SeasonController`

| Method | Route | Purpose |
|--------|-------|---------|
| `index` | `GET /admin/seasons` | List seasons with event counts |
| `create` | `GET /admin/seasons/create` | Form with clone-from dropdown |
| `store` | `POST /admin/seasons` | Create + optionally clone patterns/holidays |
| `show` | `GET /admin/seasons/{season}` | Detail with patterns and holidays |
| `activate` | `POST /admin/seasons/{season}/activate` | Set as active season |
| `storeHoliday` | `POST /admin/seasons/{season}/holidays` | Add holiday (AJAX-capable) |
| `destroyHoliday` | `DELETE /admin/holidays/{holiday}` | Remove holiday |
| `storePattern` | `POST /admin/seasons/{season}/patterns` | Add recurring pattern (AJAX-capable) |
| `destroyPattern` | `DELETE /admin/patterns/{pattern}` | Remove pattern |
| `previewGeneration` | `GET /admin/seasons/{season}/preview` | Preview all events that would be created |
| `generateEvents` | `POST /admin/seasons/{season}/generate` | Bulk-create Event records |

## Clone Feature

When creating a new season with `clone_from` set to a previous season ID:
1. All holidays from source are copied with dates shifted by `(new_year - source_year)` years
2. All patterns are copied verbatim (day_of_week, times, types, locations)

## Event Generation Algorithm (`buildSchedule()`)

```
For each pattern:
  1. Convert day_of_week (0=Mon) to Carbon dayOfWeek (0=Sun)
  2. Find first matching weekday >= season.start_date
  3. Step weekly until season.end_date
  4. For each date, check if it falls within any holiday period
     → If yes: mark skip=true with holiday name
     → If no: include in schedule
Sort all entries by date ascending
```

### Preview vs Generate
- `previewGeneration()` → calls `buildSchedule()`, renders table showing all dates (skipped ones highlighted in grey with reason)
- `generateEvents()` → calls `buildSchedule()`, creates Event records for all non-skipped entries in a transaction

### Generated Event Fields
Each event gets: title, color_hex, event_type, event_date, event_time, end_time, location, description, max_participants, estimated_cost, waiting_list_enabled=true, inscription_open_at (calculated from registration_opens_days_before), status='published', season_id, whatsapp_group_url, dive_site_id.

## Holidays

### Types
- **Recurring** (`is_adhoc = false`): School breaks (Toussaint, Noël, Carnaval, Pâques, summer)
- **Ad-hoc** (`is_adhoc = true`): One-off closures (pool maintenance, public holidays)

### Effect
Any date falling within `[holiday.start_date, holiday.end_date]` (inclusive) is skipped during generation. Already-generated events are not affected.

## Pattern Event Types

Validation restricts to: `pool`, `dive`, `training`, `theory`, `social`

(These map to the broader event_type system — specific subtypes like pool_kids, pool_pn1 are set at event level, not pattern level.)

## Relationships

- `Season` → hasMany patterns, hasMany holidays, hasMany events
- `Event.season_id` → links generated events back to their season
- `SeasonPattern.dive_site_id` → optional FK to dive_sites
