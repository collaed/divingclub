## dive-sites.md — Dive Site Database

## Overview

Admin-managed database of dive sites with rich metadata covering location, diving conditions, safety infrastructure, logistics, and media. Used by events, buddy requests, and dive groups.

## Data Model

### `dive_sites` Table (35 columns)

#### Location
| Column | Type | Purpose |
|--------|------|---------|
| `name` | varchar | Site name |
| `country` | varchar | Country |
| `region` | varchar | Region/area |
| `latitude` | decimal(10,7) | GPS latitude |
| `longitude` | decimal(10,7) | GPS longitude |

#### Diving Conditions
| Column | Type | Purpose |
|--------|------|---------|
| `max_depth` | int | Maximum depth in meters |
| `water_type` | varchar | One of 6 types (see below) |
| `conditions` | text | Current/visibility/thermocline info |
| `marine_life` | text | Notable flora/fauna |

#### Safety Infrastructure
| Column | Type | Purpose |
|--------|------|---------|
| `nearest_hospital` | text | Hospital name + address |
| `emergency_phone` | varchar | Local emergency number |
| `vhf_channel` | varchar | VHF radio channel for coast guard |
| `required_safety_equipment` | text | Mandatory equipment (e.g. SMB, DSMB) |
| `nearest_hyperbaric_chamber` | varchar | Chamber name + address |
| `hyperbaric_phone` | varchar | Chamber phone number |
| `hospital_distance_km` | smallint | Distance to nearest hospital |
| `hyperbaric_distance_km` | smallint | Distance to nearest chamber |
| `safety_notes` | text | Additional safety information |

#### Logistics
| Column | Type | Purpose |
|--------|------|---------|
| `entry_fee` | decimal(8,2) | Access cost |
| `booking_url` | varchar | Online reservation URL |
| `food_options` | text | Nearby restaurants/shops |
| `facilities` | text | Showers, parking, compressor, etc. |
| `access_notes` | text | How to get there, parking tips |
| `website_url` | varchar | Official site URL |

#### Media
| Column | Type | Purpose |
|--------|------|---------|
| `image_path` | varchar | Site photo (stored in `dive-sites/` on public disk) |
| `map_image_path` | varchar | Map/satellite image |
| `site_plan_path` | varchar | Underwater site plan (image or PDF) |
| `safety_docs_folder` | varchar | Folder path for related safety documents |

#### Status
| Column | Type | Purpose |
|--------|------|---------|
| `is_active` | bool | Whether site appears in selections |

## Water Types (6)

`sea`, `lake`, `quarry`, `river`, `pool`, `cenote`

## Controller: `Admin\DiveSiteController`

| Method | Route | Purpose |
|--------|-------|---------|
| `index` | `GET /admin/dive-sites` | List all sites |
| `create` | `GET /admin/dive-sites/create` | Create form |
| `store` | `POST /admin/dive-sites` | Save new site with file uploads |
| `edit` | `GET /admin/dive-sites/{diveSite}/edit` | Edit form |
| `update` | `PUT /admin/dive-sites/{diveSite}` | Update with file replacement |
| `destroy` | `DELETE /admin/dive-sites/{diveSite}` | Delete site + remove stored image |

### File Uploads
- Image, map, and site plan stored via `Storage::disk('public')` in `dive-sites/`
- On update, old file is deleted before storing replacement
- Max sizes: images 5MB, site plan 10MB (supports PDF)

## Model: `DiveSite`

- `events()` → HasMany Event (sites linked via `events.dive_site_id`)
- `scopeActive()` → filters `is_active = true`
- `mapsUrl()` → generates Google Maps URL (uses lat/lng if available, falls back to name search)
- Casts: `latitude`/`longitude` → decimal:7, `is_active` → boolean

## Usage Across the App

| Context | How dive sites are used |
|---------|------------------------|
| Events | `events.dive_site_id` FK — shows site info on event detail page |
| Buddy requests | `buddy_requests.dive_site_id` FK — optional site for buddy search |
| Dive groups | Planned depth validated against `dive_sites.max_depth` |
| Weather widget | Lat/lng used to fetch Open-Meteo forecast |
| Safety sheet (PDF) | Hospital/chamber distances and emergency phones printed on fiche de sécurité |
