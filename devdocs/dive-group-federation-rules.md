## dive-group-federation-rules.md — Federation Rule Engine Architecture Proposal

## Executive Summary

The current dive group proposal system is hardcoded for FFESSM/CMAS logic. To support multi-federation clubs (PADI, BSAC, SSI, LIFRAS, CMAS), we need a **strategy-based pluggable architecture** because the federations don't just have different numbers — they have fundamentally different *philosophies* about group composition.

---

## Current State Analysis

### What Works
- 2-phase algorithm (supervised groups → autonomous buddy pairs) is sound for FFESSM
- DiveGroupRule DB-driven rule matching allows parameter changes without code
- Trello-style board UI is federation-agnostic
- HomogeneityAssessmentService is independent of federation rules

### Current Limitations
1. `autonomousMaxDepth()` hardcodes FFESSM rank thresholds
2. No scope filtering — loads ALL active rules regardless of club's federation
3. Rank system (0-130 integer) maps poorly to PADI/BSAC
4. No age-based restriction enforcement (FFESSM requires 12/16/17/18 age limits)
5. `matchesDiver()` only handles rank-based conditions; named conditions (`PE20`, `OD`) in the seeder don't actually match
6. Group size is hardcoded to 3+leader; BSAC requires exactly 2 (buddy pair)
7. The concept of "group leader" doesn't exist in PADI/SSI certified diving

---

## Federation Philosophy Comparison

| Aspect | FFESSM/CMAS/LIFRAS | PADI/SSI | BSAC |
|--------|---------------------|----------|------|
| **Philosophy** | Prescriptive (law dictates) | Permissive (buddy system) | Hybrid (managed buddy pairs) |
| **Group unit** | Palanquée (1 leader + up to 4) | Buddy pair (self-organizing) | Buddy pair (always 2) |
| **Composition rules** | Legal mandate (Code du Sport) | Training ratios only | Minimum buddy grade |
| **Surface supervisor** | Directeur de Plongée (mandatory) | Professional at site (center) | Dive Manager (mandatory) |
| **Who decides groups?** | DP assigns based on prerogatives | Divers choose buddies | DM approves pairs |
| **Max group size** | 1+4 (supervised), 2-3 (autonomous) | 2 (buddy pair) | 2 (always pairs) |
| **Depth authority** | Federation level + DP decision | Certification card + personal choice | Certification + DM decision |
| **Auto-proposal** | Essential (DP needs to produce fiche) | Nice-to-have (experience matching) | Useful (validate pairs) |

---

## Federation Rules Detail

### FFESSM/CMAS (French System)

| Level | Supervised Depth | Autonomous Depth | Leader Required | Group Max |
|-------|-----------------|------------------|-----------------|-----------|
| Baptême | 6m | — | E1 instructor | 1+1 |
| PE-12 | 12m | — | GP/N4 or E1+ | 1+4 |
| PE-20 (N1) | 20m | — | GP/N4 or E1+ | 1+4 |
| PA-12 | — | 12m | Fellow PA-12+ | 2-3 |
| PA-20 | — | 20m | Fellow PA-20+ | 2-3 |
| PE-40 | 40m | — | GP/N4 or E1+ | 1+4 |
| PA-40 | — | 40m | Fellow PA-40+ | 2-3 |
| PE-60 | 60m | — | E3 (MF2)+ | 1+3 |
| PA-60 (N3) | — | 60m | Fellow N3+ | 2-3 |

Age restrictions: PA-12/PA-20 from 16, PA-40 from 17, PA-60 from 18.

### LIFRAS (Belgian System)

| Level | With P3★ | With P4★/AM+ | Autonomous | Group Max |
|-------|----------|--------------|------------|-----------|
| NB (non-breveté) | — | 15m (discovery) | — | 1+1 |
| P1★ | 20m | 20m | — | 1+4 |
| P2★ | 30m | 40m | 20m (P2+P2, 18+) | 2-3 |
| P3★ | — | — | 40m (P3+P3) | 2-3 |
| P4★ | — | — | 60m (P4+P4) | 2-3 |

### PADI/SSI (Buddy System)

| Level | Personal Depth Limit | Needs Professional? | Training Ratio |
|-------|---------------------|---------------------|----------------|
| DSD / Try Scuba | 12m | Instructor (1:4) | 4:1 |
| Scuba Diver | 12m | DM or instructor | — |
| OWD | 18m | No (buddy pair) | 8:1 training |
| AOWD | 30m | No (buddy pair) | 8:1 training |
| Rescue+ | 40m | No (buddy pair) | 8:1 training |
| Divemaster | 40m | No | Can lead tours |

Key: once certified, PADI/SSI divers choose their own buddies. The system can *suggest* pairings based on experience but cannot *mandate* them.

### BSAC (UK System)

| Grade | Max Depth | Minimum Buddy Grade | Can Manage? |
|-------|-----------|---------------------|-------------|
| Ocean Diver | 20m | Sports Diver+ | No |
| Adv. Ocean Diver | 30m | Sports Diver+ | No |
| Sports Diver | 35m (40m tech) | Sports Diver+ | No (assistant) |
| Dive Leader | 50m | Sports Diver+ | Yes (known sites) |
| Advanced Diver | 50m | Sports Diver+ | Yes (any site) |

Key constraints: **always pairs** (never trios), Dive Manager on surface, "familiar vs unfamiliar site" distinction.

---

## Proposed Architecture: Strategy Pattern

### Interface

```php
interface GroupCompositionStrategy
{
    /** Human-readable federation name */
    public function name(): string;

    /** Maximum divers per group (excluding leader if applicable) */
    public function maxGroupSize(): int;

    /** Whether this system requires a surface supervisor (DP/DM) */
    public function requiresSurfaceSupervisor(): bool;

    /** Whether the group concept is "mandatory composition" vs "suggested pairing" */
    public function isMandatory(): bool;

    /** Propose groups for an event. Returns same structure as current service. */
    public function propose(Event $event, ?int $maxDepth = null): array;

    /** Validate a single group against federation rules. Returns violation strings. */
    public function validateGroup(DiveGroup $group): array;

    /** Get the max depth a specific diver is allowed based on their cert + context */
    public function maxDepthForDiver(User $user, bool $autonomous, ?User $leader = null): int;
}
```

### Implementations

```
app/Services/GroupComposition/
├── GroupCompositionStrategy.php       (interface)
├── FfessmStrategy.php                 (FFESSM/CMAS/LIFRAS — prescriptive)
├── PadiStrategy.php                   (PADI/SSI — permissive)
├── BsacStrategy.php                   (BSAC — hybrid)
└── Resolver.php                       (factory: club config → strategy)
```

### Strategy Behaviors

| Method | FFESSM | PADI/SSI | BSAC |
|--------|--------|----------|------|
| `maxGroupSize()` | 4 | 1 (buddy pair) | 1 (buddy pair) |
| `requiresSurfaceSupervisor()` | true (DP) | false (center handles) | true (DM) |
| `isMandatory()` | true (legal) | false (suggestions) | true (buddy rules) |
| `propose()` | Current 2-phase algorithm | Experience-match buddy pairs | Grade-match buddy pairs |
| `validateGroup()` | Leader rank + depth + age | Only depth per diver | Buddy grade + depth + pair-only |

### Resolver (Factory)

```php
class Resolver
{
    public static function forClub(): GroupCompositionStrategy
    {
        $federation = ThemeSetting::get('primary_federation', 'FFESSM');

        return match ($federation) {
            'FFESSM', 'CMAS', 'LIFRAS' => new FfessmStrategy(),
            'PADI', 'SSI' => new PadiStrategy(),
            'BSAC' => new BsacStrategy(),
            default => new FfessmStrategy(),
        };
    }
}
```

### FfessmStrategy (keeps current logic, refined)

The current `DiveGroupProposalService` becomes `FfessmStrategy` with:
- Scope filtering: only load rules where `scope IN ('global', 'FFESSM', {club_federation})`
- Age validation: check `member_details.date_of_birth` against minimum ages
- Remove hardcoded `autonomousMaxDepth()` — use rules DB instead

### PadiStrategy (new — lightweight)

```php
public function propose(Event $event, ?int $maxDepth = null): array
{
    // Group divers by experience level, then pair them
    // No leader requirement — just match similar-level buddies
    // Sort by: cert rank, total dives, last dive recency
    // Produce buddy pairs with depth = min(diver1.max, diver2.max, site.max)
}

public function validateGroup(DiveGroup $group): array
{
    $violations = [];
    // Only check: nobody exceeds their personal depth limit
    // Warn if group > 2 (not standard buddy pair)
    // Warn if cert level gap is large (not mandatory, just advisory)
}
```

### BsacStrategy (new — strict pairs)

```php
public function propose(Event $event, ?int $maxDepth = null): array
{
    // ALWAYS pairs (never trios)
    // Each diver's buddy must be at least Sports Diver grade
    // Exception: Sports Divers can buddy with each other
    // Ocean Divers MUST have a Sports Diver+ buddy
    // Sort by grade, pair weakest with strongest available
}

public function validateGroup(DiveGroup $group): array
{
    $violations = [];
    if ($group->members->count() !== 2) {
        $violations[] = 'BSAC requires buddy PAIRS only (exactly 2 divers)';
    }
    // Check minimum buddy grade for each member
    // Check depth doesn't exceed lowest-qualified member's limit
}
```

---

## Migration Path

### Phase 1: Refactor (non-breaking)
1. Extract current `DiveGroupProposalService` logic into `FfessmStrategy`
2. Create interface + Resolver
3. Make `DiveGroupProposalService` delegate to `Resolver::forClub()`
4. Add `primary_federation` to theme_settings (default: `FFESSM`)
5. Fix the scope filtering issue

### Phase 2: Implement PADI/SSI strategy
6. Create `PadiStrategy` with buddy-pair matching
7. Adjust UI: hide "leader" role concept for PADI clubs (all are "divers")
8. Change validation messages to advisory (not blocking)

### Phase 3: Implement BSAC strategy
9. Create `BsacStrategy` with strict pair-only logic
10. Enforce group size = 2 at UI level when BSAC active
11. Add "Dive Manager" role (surface-only, not a group member)

### Phase 4: Rules DB cleanup
12. Fix `matchesDiver()` to handle named conditions (PE20, OD, etc.)
13. Add `minimum_age` column to `dive_group_rules`
14. Add scope filtering index
15. Make `diver_condition` an enum or structured JSON instead of free-text

---

## Configuration

New `theme_settings` keys:
```
primary_federation = FFESSM|PADI|SSI|BSAC|CMAS|LIFRAS
group_composition_mode = mandatory|advisory
allow_group_size_override = true|false
enforce_age_limits = true|false
```

---

## Impact on Existing Code

| Component | Change Required |
|-----------|-----------------|
| `DiveGroupProposalService` | Becomes thin wrapper delegating to strategy |
| `DiveGroupController::propose()` | No change (same interface) |
| `DiveGroupController::validateGroups()` | Use strategy.validateGroup() instead of direct rule checks |
| `DiveGroupRule` model | Keep as parameter store, add scope index |
| `DiveGroupRuleSeeder` | Split into per-federation seeders |
| Blade views | Conditional: show/hide "leader" field based on strategy |
| PDF (fiche de sécurité) | FFESSM: current format. PADI: simplified. BSAC: pair-based. |

---

## Open Questions

1. **Mixed-federation clubs**: CEP has members from FFESSM, LIFRAS, PADI, CMAS. Should the group engine use the *strictest* applicable rule or the *club's primary* federation?
   - **Recommendation**: Use club's primary federation for proposals, but validate each diver against their *own* federation's personal limits.

2. **Cross-federation equivalence**: If a PADI AOWD joins an FFESSM club dive, what are they? The `equivalence_group` in `certification_levels` already handles this — map to the nearest FFESSM equivalent for rule matching.

3. **Overridable vs mandatory**: Should bureau be able to override federation rules?
   - **Recommendation**: Yes, with audit trail. Add `override_reason` field. Some clubs operate under stricter house rules.
