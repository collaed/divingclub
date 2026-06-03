## events.md — Event Lifecycle

## Event Types (10)

`pool`, `pool_kids`, `pool_pn1`, `pool_pn23`, `apnea`, `fosse`, `quarry`, `long_trip`, `theory`, `social`

## Event Fields

Core: title, event_type, event_date, end_date, event_time, end_time, location, description, max_participants, status (scheduled/published/cancelled), dive_site_id.

Financial: estimated_cost, deposit_1/2/3_date + amount.

Settlement: trip_settlement_enabled, driver_bounty_total, local_daily_charge, settlement_status (open/closed).

Federation: is_federated, external_slots.

Links: whatsapp_group_url, participant_email (auto-generated: `event-{id}@clubcep.eu`).

## Registration Flow

```
Member clicks "Register"
  → EventController::register()
    → Check isRegistrationOpen() (inscriptions_closed, inscription_open_at)
    → Check hasDiveProfile() (pool/dive/training types require DOB, sex, mobile, emergency)
    → Check MedicalComplianceService::isCompliant() (pool/dive/training types)
    → If event full + waiting_list_enabled: create with status=waiting, assign position
    → Else: create with status=confirmed
    → If trip_settlement_enabled: auto-create TripParticipant
    → If deposits configured: create PaymentExpected
    → Flash success message
```

## Waiting List Auto-Promotion

When a confirmed member cancels:
1. Find next waiting registration (lowest `waiting_list_position`)
2. Update to `status=confirmed`, clear position
3. Reorder remaining waiting positions

## Medical Gate

For event types `pool`, `dive`, `training`:
- `MedicalComplianceService::isCompliant(user, event_date)` must return true
- Checks the user's federation-specific rules (FFESSM: cert valid 1 year, under-40 no cert needed if recent exam; PADI: no cert required)
- If expired but within grace period: registers with warning flash
- If hard-expired: blocks with error

## Dive Groups (Palanquées)

Per-event buddy group management:
- `DiveGroup` has name, max_depth, belongs to event
- `DiveGroupMember` links user to group, `is_leader` flag
- 14 rules in `dive_group_rules` table (cert level gaps, depth restrictions, leader requirements)
- `DiveGroupProposalService::propose()` auto-generates valid groups
- `SwapSuggestionService::suggest()` recommends member swaps
- Print view: `fiche-securite-pdf.blade.php` (A4 safety sheet with emergency info)

## Event Photos

- Members upload photos to events (`event_photos` table)
- Gallery component with fullscreen viewer
- `SocialPublishService` auto-posts eligible photos to Facebook/Instagram
- Quality check via `ImageQualityService::score()`

## Calendar

- Month/week/day views
- Color-coded by event_type (configurable hex per type)
- iCal feed at `/calendar/feed` (CalendarFeedController)
- Recurring patterns via `season_patterns` table

## Instructor Calendar

- Weekly grid showing instructor availability
- 10 activity types with color coding
- `InstructorAvailability` model: user_id, date, activity_type
- AJAX toggle (click to add/remove availability)
- Auto-registers instructor for the event when marking available
- Instructor initials + colors stored in `member_details.instructor_initial` / `instructor_color`

## Event Email

- `participant_email` auto-generated on creation: `event-{id}@clubcep.eu`
- Bureau/instructors can email all confirmed participants
- Visible at bottom of event detail page (only for bureau/instructors)
- Handled by `MailAliasService::eventParticipants()`

## External Registrations (Federation API)

- Partner clubs can register external divers via API
- `external_registrations` table tracks these separately
- Status flow: pending → approved/rejected (by bureau)
- Slot limit: `external_slots` on event
