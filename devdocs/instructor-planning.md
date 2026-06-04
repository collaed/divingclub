## instructor-planning.md — Instructor Availability Calendar

## Overview

Weekly calendar where instructors mark their availability for training events. Uses AJAX toggle — clicking an event cell adds/removes availability instantly. Marking available auto-registers the instructor for the event.

## Data Model

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `instructor_availabilities` | Instructor↔event availability | user_id, event_id, date, slot, activity_type, note |
| `member_details` (fields) | Instructor display config | instructor_initial (varchar 3), instructor_color (hex), active_instructor (bool) |

Unique constraint: `(user_id, date, slot, activity_type)` — one entry per instructor per timeslot per activity.

## Activity Types (13)

| Slug | Color | Icon | Label |
|------|-------|------|-------|
| `pool` | #4a86c8 | 🏊 | Pool |
| `pool_kids` | #2ecc71 | 👶 | ↳ Kids |
| `pool_pn1` | #1a237e | 1️⃣ | ↳ PN1 |
| `pool_pn23` | #e74c3c | 🔴 | ↳ PN2+ |
| `pool_swimming` | #ff9800 | 🏊‍♂️ | ↳ Swimming |
| `training` | #5c6bc0 | 🤿 | Training |
| `apnea` | #00c853 | 🫁 | Apnea |
| `fosse` | #00695c | 🕳️ | Fosse |
| `quarry` | #00bcd4 | 🪨 | Quarry/Lake |
| `long_trip` | #f9a825 | ✈️ | Long Trip |
| `theory` | #78909c | 📖 | Theory |
| `social` | #e91e63 | 🎉 | Social |
| `closed` | #9e9e9e | 🚫 | Closed |

Colors defined in `InstructorAvailabilityController::ACTIVITY_COLORS` and rendered in `_planning.scss`.

## Controller: `InstructorAvailabilityController`

| Method | Route | Purpose |
|--------|-------|---------|
| `index` | `GET /availability?month=YYYY-MM` | Monthly calendar view |
| `toggle` | `POST /availability/toggle` | AJAX: add/remove availability for an event |

### Access Roles
- **View**: all authenticated members
- **Toggle**: instructor, instructor_apnea, bureau_master, bureau_technical, assistant

## Toggle Behavior

```
POST /availability/toggle {event_id}
  → If past event: 422 error
  → If already available:
      → Delete availability
      → Cancel event registration (if any)
      → Return {status: "removed"}
  → If not available:
      → Create InstructorAvailability record
      → Auto-register for event (if registration open, not already registered)
      → Return {status: "added"}
```

Auto-registration respects waiting list logic (confirmed vs waiting).

## Calendar View

- Month-based grid showing all events in the period
- Multi-day events expanded into each calendar day
- Events grouped by date, sorted by time
- Each instructor shown with their `instructor_initial` and `instructor_color`
- Side-by-side display when multiple events fall on the same day

## Wednesday Pool Blocks

Two timeslots per Wednesday (17:00–18:30 and 18:30–20:00), created as separate events. The `event_type` determines which group gets tank priority (PN1, kids, or generic pool). Both blocks typically share the same type each week.

## Instructor Initials (Stable, Manually Assigned)

Stored in `member_details.instructor_initial`. Not auto-generated — they match the legacy Google Sheet planning. Notable disambiguation:
- Jerome Samson = J, Jerome Tongio = T, Jérôme Boisseau = B
- Pietro = O (not P, which is Pascale)
- Manuel = U, Valérie = A, Luc = C

## Legend

Displayed below the calendar. Two categories:
1. **Instructors** — members with Spatie role `instructor` or `instructor_apnea`
2. **Bureau non-instructors** — bureau roles without instructor role

Each shown with their initial + color badge.
