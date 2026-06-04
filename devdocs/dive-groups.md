## dive-groups.md — Dive Group Planner

## Overview

Trello-style drag-and-drop interface for organizing dive buddy pairs ("palanquées") per event. Auto-proposal engine respects 14 federation rules (FFESSM/CMAS) on group composition, depth limits, and leader qualifications. Produces the "fiche de sécurité" safety sheet.

## Data Model

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `dive_groups` | Group definitions per event | id, event_id, name, dive_mode (supervised/autonomous), purpose, planned_depth, planned_duration, gas_mix, line_number, planned_entry_time, planned_exit_time, notes, created_by |
| `dive_group_members` | Diver↔group assignments | dive_group_id, user_id, role (leader/member) |
| `dive_group_rules` | Federation rule definitions | name, scope, diver_condition, dive_mode, min_leader_rank, leader_category, max_depth, max_group_size, description, is_active |

## Dive Modes

- **supervised** — led by an instructor (E1-E4), divers below autonomous level
- **autonomous** — led by a strong diver (PA40/N3+), all members at autonomous level

## Member Roles (in group)

- `leader` — responsible for the group, must meet min_leader_rank
- `member` — regular diver assigned to follow the leader

## Controller: `DiveGroupController`

Extensive controller (424 lines) handling:

| Action | Purpose |
|--------|---------|
| Index per event | Display Trello-style board of groups + unassigned pool |
| Create group | Manual group creation with dive parameters |
| Auto-propose | Run `DiveGroupProposalService` to generate optimal groups |
| Add/remove member | Drag-and-drop member between groups |
| Edit group params | Update depth, duration, gas, entry/exit times |
| Delete group | Dissolve group, members return to unassigned pool |
| Safety sheet (PDF) | Generate "fiche de sécurité" for the dive director |

## `DiveGroupProposalService` Algorithm

### Phase 1: Supervised Groups (weakest divers first)
1. Build profile for each confirmed participant (rank, certifications, can_lead flag)
2. Separate into leaders (instructors + high-rank) and regular divers
3. Sort divers by rank ascending (weakest first — they need most supervision)
4. For each leader (strongest first):
   - Assign up to 3 divers that this leader can supervise at the planned depth
   - Match using `findRule()` against active `dive_group_rules`
   - Create supervised group

### Phase 2: Autonomous Groups (remaining qualified divers)
1. Filter unassigned divers with rank ≥ 30 (N2+/PA20+)
2. Sort by rank descending (strongest leads)
3. Group into pairs or triplets (max 3 for autonomous)
4. Respect autonomous depth limits from rules

### Output
```php
['groups' => [...], 'unassigned' => [...], 'warnings' => [...]]
```

Warnings include: "No rule found for diver X at depth Y", "Insufficient leaders", etc.

## Rules Engine (`dive_group_rules`)

14 active rules covering:
- Beginner divers (N1/PE20) — require E2+ leader, max depth 20m, max group 4
- Intermediate (N2/PA20) — require GP/N4+ or autonomous if paired, max 40m
- Advanced (N3/PA60) — autonomous, max 60m
- Instructor-led training — specific rank requirements
- Night dives, decompression, enriched air — special constraints

### Rule Fields
- `scope` — which context (open_water, pool, training)
- `diver_condition` — certification level requirement for group members
- `dive_mode` — supervised or autonomous
- `min_leader_rank` — minimum rank integer the leader must have
- `leader_category` — instructor, GP (guide de palanquée), or diver
- `max_depth` — maximum allowed depth in meters
- `max_group_size` — maximum divers per group (leader excluded)

## Admin: `DiveGroupRuleController`

Bureau can view, create, edit, and deactivate rules via `/admin/dive-group-rules`.

## Seeder: `DiveGroupRuleSeeder`

Seeds the 14 standard FFESSM/CMAS rules used by French-system clubs.

## UI

Trello-style board:
- Each column = one dive group (with depth, duration, gas shown)
- Cards = divers (showing name, cert level, role badge)
- Unassigned pool at the left
- Drag cards between columns to reassign
- "Auto-propose" button runs the algorithm and populates groups
