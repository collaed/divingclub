## equipment.md — Equipment Management

## Inventory

`equipment` table: id, name, type, status, short_number, size, location, notes + tank-specific fields.

### Types
`tank`, `bcd`, `regulator`, `wetsuit`, `fins`, `mask`, `computer`, `light`, `other`

### Statuses
`available`, `on_loan`, `maintenance_required`, `retired`

### Locations (`Equipment::LOCATIONS`)
`pool`, `local`, `other` — stored as locale-neutral keys, translated via `__()`
at render time. Was previously free text with hardcoded French options
(`'Entrepôt'`, `'Piscine Merl'`, `'Hors Service'`); `'Hors Service'` was really
an out-of-service state, not a place — that's now covered by the maintenance
substatus below instead.

### Maintenance dates vs. the maintenance-rule engine
`last_maintenance_date`/`maintenance_due_date` are simple, directly-editable
columns on `equipment` for lightweight "last serviced / next due" tracking at
the individual-unit level — independent of (not a replacement for) the
per-type task engine under "Maintenance Scheduling" below. `maintenance_due_date`
in the past drives `Equipment::isMaintenanceOverdue()`, shown as a "Maintenance
overdue" badge alongside (not instead of) the main status badge, and folded
into `isAvailable()` so an overdue unit can't be loaned even while
`status === 'available'`.

### Size (`size`)
Free-text, e.g. `XXXS`…`XXL`, plus brand-specific oddities (`XLS`, `MD`, `ML`).
Not an enum — BCD/jacket sizing isn't standardized enough across brands for a
fixed list, unlike `location`. Shown as a badge next to the name on the index
and show pages, same treatment as tank `volume`.

### Child mouthpiece / child sizing (`is_child_sized`)
One generic flag reused across equipment types rather than adding a
regulator-specific column: for BCDs/wetsuits it means the item itself is
child-sized; for a regulator it means a child-sized mouthpiece is currently
fitted. Both cases answer the same practical question ("can a child use this
as-is?"), surfaced with the same 👶 badge.

### Short Numbers
Each equipment item has a unique short number (e.g., "B12" for BCD #12, "T07" for tank #7). Used for quick identification on dive boats.

### Tank-Specific Fields

| Column | Type | Purpose |
|--------|------|---------|
| `club_id` | varchar(10) | Club inventory code |
| `brand` | varchar | Tank brand/model |
| `manufacturer` | varchar | Manufacturer name |
| `threading` | varchar(20) | Valve threading (DIN/INT/M25) |
| `manufacture_date` | date | Tank manufacture date (stamped) |
| `weight_kg` | decimal(5,1) | Empty weight in kg |
| `volume` | varchar(20) | Tank volume (e.g. "12L", "15L") |
| `material` | varchar(20) | Steel or aluminum |
| `test_pressure_bar` | smallint | Test pressure in bar |
| `working_pressure_bar` | smallint | Working pressure in bar |
| `last_retest_date` | date | Date of last hydrostatic retest |
| `next_retest_date` | date | Calculated next retest due date |
| `last_inventory_date` | date | Last physical inventory check |
| `last_seen_at` | date | Last time equipment was scanned/used |

### General Equipment Fields

| Column | Type | Purpose |
|--------|------|---------|
| `serial_number` | varchar | Manufacturer serial number |
| `purchase_date` | date | When the club bought it |
| `condition` | varchar | Physical condition notes |
| `is_loanable` | bool | Whether available for member loans |
| `is_child_sized` | bool | Junior/kids equipment flag |
| `is_cold_water` | bool | Suitable for cold water diving |
| `deleted_at` | timestamp | Soft delete for retired equipment |

## Loan System

`equipment_loans` table: equipment_id, user_id, loaned_at, returned_at, expected_return_date, loaned_by, returned_by.

### Checkout Flow
```
Admin selects equipment → enters user_id → POST /admin/equipment/{id}/loan
  → EquipmentController::loan()
    → Check equipment.isAvailable() (status === 'available')
    → If status !== 'available' → error (blocks maintenance_required items)
    → Create EquipmentLoan record
    → Update equipment.status = 'on_loan'
```

### Quick Loan
`POST /admin/equipment/quick-loan` — select equipment type, system suggests available items sorted by size match for the borrowing member (BCD size from profile).

### Return Flow
```
Admin clicks Return → POST /admin/equipment/return/{loan}
  → EquipmentController::returnLoan()
    → Set loan.returned_at = now()
    → Check equipment.hasOverdueMaintenance()
    → If overdue: set status = 'maintenance_required'
    → Else: set status = 'available'
```

## Maintenance Scheduling

### Rules (`equipment_maintenance_rules`)

| Column | Type | Purpose |
|--------|------|---------|
| `equipment_type` | varchar | Which type this rule applies to |
| `maintenance_name` | varchar | Name of the check (e.g. "Visual Inspection") |
| `interval_months` | int | How often (months between checks) |
| `is_mandatory` | bool | Whether this blocks equipment use if overdue |
| `regulation_reference` | varchar | Legal/regulatory reference (e.g. "EN 1089-1") |

Per equipment type, defines mandatory intervals:
- Tanks: visual inspection every 12 months, hydrostatic test every 60 months
- Regulators: annual service every 12 months
- BCDs: annual inspection every 12 months

### Tasks (`equipment_maintenance`)
equipment_id, maintenance_name, due_date, completed_at, completed_by, is_mandatory, notes.

When a maintenance rule is configured, the system auto-generates the next due task after completion.

### `hasOverdueMaintenance()` (Equipment model)
Returns true if any mandatory maintenance task has `due_date < now()` and `completed_at = null`.

### Maintenance Gate
Equipment with overdue mandatory maintenance:
- Status flipped to `maintenance_required` on return
- Cannot be loaned out (`isAvailable()` returns false)
- Shows in dashboard overdue count

## Reminders

`SendEquipmentReminders` job (daily at 09:00):
- Finds overdue loans (past expected_return_date or > max_days)
- Sends reminder email to borrower

## Admin UI

- Sortable table with filters: type, status, location, size (text search)
- Clickable rows (data-href) to equipment detail page
- Detail page: loan history, maintenance history, current loan
- Bulk actions: none (individual management only)
