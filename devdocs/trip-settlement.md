## trip-settlement.md — Trip Cost-Splitting Engine

## Scope

Only for `long_trip` events with `trip_settlement_enabled = true`. Handles shared expenses for multi-day dive trips (van rental, fuel, tolls, groceries).

## Data Model

- `events.trip_settlement_enabled` — boolean, activates the feature
- `events.driver_bounty_total` — total € bounty pool for all drivers (e.g. €600 for 3 vans × 4 legs × €50)
- `events.local_daily_charge` — daily € charge for non-van members using local transport
- `events.settlement_status` — `open` (receipts allowed) or `closed` (ledger locked)
- `event_registrations.transit_mode` — `van`, `fly`, `own` (chosen at registration)
- `trip_participants` — user_id, event_id, driving_percentage (0-100), local_transit_days
- `trip_receipts` — user_id, event_id, amount, approved_amount, category, image_path, status, reviewer_notes

## Receipt Categories

- `general` — shared equally among ALL participants (groceries, group dinners, communal deposits)
- `transit` — shared only among VAN riders (fuel, tolls, van rental, parking)
- `diving` — club-level dive invoices (dive center charges); appears in accounting but individual charges use `individual`
- `individual` — charged to a specific participant (e.g. extra dives, personal expenses); with `is_third_party=true`, it's a charge TO the person (increases what they owe)
- `memo` — audit trail only; never counted in any settlement pool (e.g. auto-generated club advance records)

## Receipt Status Flow

`pending` → `approved` (treasurer verifies amount + image) or `rejected` (with reviewer_notes reason)

## 5-Step Algorithm (`TripSettlementService::calculate()`)

### Step 1: Global Pool
```
Sum all approved receipts where category = 'general'
Divide equally among ALL trip_participants
→ global_share = global_pool / participant_count
```

### Step 2: Local Transit Subsidy
```
For each non-van participant:
  local_subsidy += local_transit_days × local_daily_charge
```
This money subsidizes the van pool (non-van members pay for local van usage).

### Step 3: Long-Haul Transit Pool
```
transit_pool = sum of approved receipts where category = 'transit'
```

### Step 4: Driver Bounties
```
total_bounties = driver_bounty_total (if any driving_percentage > 0)
Per driver: bounty_credit = driver_bounty_total × (their driving_percentage / total_driving_pct)
```
Example: driver_bounty_total = €600, Alice 50%, Bob 30%, Carol 20% → Alice gets €300, Bob €180, Carol €120.

### Step 5: Final Balance
```
net_transit_cost = transit_pool + total_bounties - local_subsidy
transit_share = net_transit_cost / van_rider_count  (only van riders pay this)

Per participant:
  owes = global_share + (is_van ? transit_share : local_charge)
  credits = bounty_credit + total_paid (their approved receipts)
  balance = owes - credits
```

- **Positive balance** = member owes the club
- **Negative balance** = club owes the member

## Money Conservation Invariant

```
Σ(all participant balances) = 0
```
Tests verify this. If it's not zero, the algorithm has a bug.

## UI

### Member View (`/events/{id}/settlement`)
- Balance card (owes or is owed)
- Receipt upload form (amount, category, photo/PDF)
- List of own receipts with status badges
- Full settlement overview table (all participants)

### Treasurer View (`/events/{id}/settlement/manage`)
- Summary cards: global pool, transit pool, driver bounties, local subsidy
- Pending receipts queue with inline approve (editable amount + category) / reject (with reason)
- Participant editor: driving_percentage + local_transit_days per person
- Full ledger table with color-coded balances
- Close/reopen ledger button

## Registration Integration

When a member registers for a trip with settlement enabled:
- `transit_mode` field shown in registration form (van/fly/own)
- Stored on `event_registrations.transit_mode`
- `TripParticipant` auto-created

## Example: Juan-les-Pins 2026

- 24 participants: 19 van, 5 own car
- 3 vans, driver_bounty_total = €600
- local_daily_charge = €10/day
- Expected transit costs ~€4,500 / 19 van riders ≈ €236/person
