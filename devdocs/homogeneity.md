## homogeneity.md — Dive Group Homogeneity Assessment

## Overview

Evaluates how well-matched a group of divers is for diving together. Produces a 0–100 score with Green/Orange status, detailed factors explaining score deductions, and recommendations. Used by the dive group planner to warn when palanquées are mismatched.

## Architecture (5 files in `Services/Homogeneity/`)

| File | Purpose |
|------|---------|
| `HomogeneityAssessmentService` | Main engine — assess() entry point |
| `HomogeneityPolicy` | Configurable thresholds (warning/strong gaps) |
| `DiveContext` | Dive conditions (planned_depth, water_temp, environment) |
| `HomogeneityFactor` | Single score factor (type, impact, label, detail, related divers) |
| `HomogeneityAssessmentResult` | Final result (score, status, factors[], recommendations[]) |

## Entry Point

```php
$service = new HomogeneityAssessmentService(new HomogeneityPolicy);
$result = $service->assess($divers, new DiveContext(plannedDepth: 30, waterTempCelsius: 14.0, environment: 'quarry'));
// $result->score: 0-100
// $result->status: Green | Orange
// $result->factors: HomogeneityFactor[]
// $result->recommendations: string[]
```

## Diver Profile Input

Each diver is an array with:
| Key | Type | Purpose |
|-----|------|---------|
| `airConsumption` | float 0-1 | Normalized air consumption rate |
| `easeLevel` | float 0-1 | Comfort/ease in water |
| `primaryIntent` | string | Dive purpose (exploration, training, photo, fun) |
| `isPhotographer` | bool | Photographer flag |
| `certRank` | int | Certification level rank |
| `totalDives` | int | Lifetime dive count |
| `lastDiveWeeksAgo` | int | Weeks since last dive |
| `age` | int | Age in years |
| `isFragile` | bool | Special health considerations |

## Scoring Algorithm

### Phase 1: Pair-wise Comparisons

For every pair of divers (i, j):
- **Air consumption gap**: warns at 0.25 difference, strong penalty at 0.40
- **Ease level gap**: warns at 0.20, strong at 0.35
- **Intent mismatch**: penalty when primary_intent differs
- **Photographer mixing**: penalty when photographer paired with non-photographer (different pace expectations)

Pair penalties scaled by pair count: `pairScale = min(1.0, 3 / pairCount)`. Reference: 3 divers = 3 pairs = scale 1.0. Prevents larger groups from accumulating disproportionate penalties.

### Phase 2: Group-level Factors

Applied once (not per-pair):
- Experience disparity across the group
- Fragile diver considerations
- Cold water + depth combinations

### Phase 3: Family Caps

Factor types are grouped by family prefix:
| Family | Max Total Penalty |
|--------|-------------------|
| `air` | -30 |
| `ease` | -30 |
| `intent` | -25 |

Prevents opaque double-penalty stacking (e.g., two air-related factors can't exceed -30 total).

### Final Score

```
score = 100 + sum(all factor scoreImpacts)
score = max(0, min(100, score))
status = score > policy.orangeThreshold(79) ? Green : Orange
```

## Policy Configuration

```php
new HomogeneityPolicy(
    airGapWarning: 0.25,    // Air consumption difference to start penalizing
    airGapStrong: 0.40,     // Air consumption difference for strong penalty
    easeGapWarning: 0.20,   // Ease level difference to start penalizing
    easeGapStrong: 0.35,    // Ease level difference for strong penalty
    fragileEaseThreshold: 0.70,  // Ease level below which fragile flag matters
    orangeThreshold: 79,    // Score at or below which status becomes Orange
)
```

## Status Levels

| Status | Score Range | Meaning |
|--------|-------------|---------|
| Green | 80–100 | Group is well-matched |
| Orange | 0–79 | Potential issues — review recommendations |

**Note**: No RED status in this layer. Red requires explicit club policy decisions beyond algorithmic assessment.

## Edge Cases

- 0 divers → score 0, Orange, "Aucun plongeur" message
- 1 diver → score 100, Green, no factors (trivially homogeneous)

## DiveContext

```php
new DiveContext(
    plannedDepth: 30,          // meters
    waterTempCelsius: 14.0,    // degrees Celsius
    environment: 'quarry',     // quarry | sea | lake
)
```

Environment and conditions influence factor severity (e.g., cold water amplifies air consumption differences).
