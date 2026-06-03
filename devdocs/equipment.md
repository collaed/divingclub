## equipment.md — Equipment Management

## Inventory

`equipment` table: id, name, type, status, short_number, size, location, notes.

### Types
`tank`, `bcd`, `regulator`, `wetsuit`, `fins`, `mask`, `computer`, `light`, `other`

### Statuses
`available`, `on_loan`, `maintenance_required`, `retired`

### Short Numbers
Each equipment item has a unique short number (e.g., "B12" for BCD #12, "T07" for tank #7). Used for quick identification on dive boats.

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
