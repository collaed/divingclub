## dive-data.md — Dive Data Import & Export

## Overview

Members upload UDDF files from dive computers to populate actual dive parameters (depth, duration). Bureau exports club dive data as UDDF or DAN DL7 format for research programs.

## Formats

| Format | Standard | Direction | Audience |
|--------|----------|-----------|----------|
| UDDF 3.2.1 | Universal Dive Data Format (XML) | Import + Export | Individual members |
| DAN DL7 | DAN Project Dive Exploration (pipe-delimited text) | Export only | Bureau → DAN research |

## Controller: `DiveDataController`

| Method | Route | Purpose |
|--------|-------|---------|
| `importUddf` | `POST /dive-data/import` | Upload UDDF XML, match dives to events |
| `exportUddf` | `GET /dive-data/export/uddf` | Generate UDDF from user's dive log |
| `exportDan` | `GET /dive-data/export/dan?year=2026` | DAN DL7 export (bureau only) |

## UDDF Import Flow

```
Member uploads .uddf/.xml file (max 10MB)
  → UddfService::parse() extracts dive profiles
  → For each dive:
    1. Match to Event by date (event_date = dive date)
    2. Find user's DiveGroupMember for that event
    3. Update dive_group with actual_depth and actual_duration
  → Return count of matched dives
```

## `UddfService`

### `parse(string $xmlContent): array`

Returns:
```php
[
  'dives' => [
    ['datetime' => Carbon, 'max_depth' => float, 'duration_minutes' => int, 'min_temp' => float, ...]
  ],
  'divers' => [...],
  'sites' => ['site_id' => ['name', 'latitude', 'longitude', 'max_depth']]
]
```

Parses UDDF 3.2.x XML structure:
- `<divesite>` → site metadata
- `<profiledata><repetitiongroup><dive>` → individual dives
- `<informationbeforedive>` → date, time, site reference
- `<samples><waypoint>` → profile data (depth, time, temp)

### `export(User $user, Collection $memberships): string`

Generates complete UDDF 3.2.1 XML document with:
- Generator metadata (DivingClub Manager)
- Diver profile from user details
- Dive sites from associated events
- Dive profiles (one per group membership)

## `DanExportService`

### `export(Collection $diveGroupMembers): string`

Generates DAN DL7 pipe-delimited text with three record types:

**File Header:**
```
DL7|20260604|DivingClub Manager 1.0|
```

**ZDH (Diver Header)** — one per member:
```
ZDH|user_id|last_name|first_name|date_of_birth|sex||
```

**ZDL (Dive Log)** — one per dive:
```
ZDL|date|time|max_depth||site_name|country|water_type|dive_mode|||
```

**ZDT (Diver Trailer)** — dive count:
```
ZDT|dive_count|
```

## Data Dependencies

Import relies on:
- Events existing with correct dates
- User being assigned to a DiveGroup for that event
- DiveGroupMember linking user → group → event

Export relies on:
- DiveGroupMember records with loaded diveGroup.event.diveSite
- User detail for demographic data (DAN format)

## Validation

- Upload: `mimes:xml,uddf`, max 10MB
- DAN export: filtered by year parameter (defaults to current year)
