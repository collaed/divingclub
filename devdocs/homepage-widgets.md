## homepage-widgets.md — Homepage Widget System

## Overview

Configurable widget layout with drag-and-drop for the club homepage. Widgets have per-role visibility (public/members/instructors/bureau). Layout stored as JSON in `theme_settings`.

## Data Model

Layout stored in `theme_settings` table under key `homepage_layout` as a JSON array of widget objects.

### Widget Object Schema
```json
{
  "type": "articles",
  "enabled": true,
  "zone": "main",
  "visibility": "public",
  "config": {"limit": 10}
}
```

## Widget Types (7)

| Type | Icon | Label | Allowed Zones | Config |
|------|------|-------|---------------|--------|
| `hero` | 🖼️ | Hero Slideshow | top | height, title, subtitle |
| `welcome` | 👋 | Welcome Text | main, top | text |
| `articles` | 📰 | Article Stream | main | limit |
| `upcoming_events` | 📅 | Upcoming Events | main, sidebar | limit |
| `quick_links` | 🔗 | Quick Links | sidebar | — |
| `photos` | 📸 | Photo Gallery | sidebar, main | count |
| `custom_html` | ✏️ | Custom HTML | main, sidebar, top | html |

## Zones

- **top** — Full-width above content (hero slideshow)
- **main** — Primary content column
- **sidebar** — Right sidebar column

## Visibility Levels

| Value | Who sees it |
|-------|-------------|
| `public` | Everyone (guests + members) |
| `members` | Authenticated users only |
| `instructors` | Bureau + instructors |
| `bureau` | Bureau roles only |

## Controller: `HomepageLayoutController`

| Method | Purpose |
|--------|---------|
| `defaultLayout()` | Returns factory-default widget array |
| `getLayout()` | Loads saved layout, merges any new default widget types |
| `saveLayout(Request)` | AJAX endpoint — saves layout JSON to theme_settings |
| `isVisibleTo(widget, user)` | Checks if widget should render for current user |
| `loadWidgetData(widget)` | Resolves widget's data (articles, events, photos, links) |

### Save Route
`POST /admin/homepage/layout` — requires `manage settings` permission.

## Data Loading per Widget Type

| Widget | Data Source |
|--------|-------------|
| `hero` | `EventPhoto::randomForMembers()` or `randomPublic()` |
| `articles` | `Article::active()->where('is_public', true)` excluding classifieds, ordered by created_at |
| `quick_links` | `Link::where('is_public', true)->orderBy('sort_order')` |
| `photos` | Same as hero (EventPhoto random selection) |
| `upcoming_events` | `Event::where('event_date', '>=', now())` with registration counts (members only) |
| `custom_html` | Raw HTML from config field |
| `welcome` | Static text from config field |

## Drag-and-Drop

Bureau admins enter edit mode on the homepage. Widgets can be:
- Reordered within their zone
- Enabled/disabled (toggle)
- Configured (per-widget settings modal)

Changes saved via AJAX to `theme_settings.homepage_layout`.

## Default Layout Merging

When new widget types are added to `defaultLayout()`, `getLayout()` automatically appends them to saved layouts that don't include them. This ensures upgrades add new widgets without losing existing configuration.
