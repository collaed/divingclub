<?php

/**
 * Dive Group Proposal Service
 *
 * Automatically proposes dive group compositions for an event based on the
 * registered participants' certification levels and the active federation
 * rules (FFESSM/CMAS). Produces a "fiche de sécurité" — the safety sheet
 * that French-system dive clubs must prepare before every open-water dive.
 *
 * Algorithm:
 *  1. Separate participants into instructors and divers.
 *  2. Sort divers by rank ascending (weakest first — they need the most supervision).
 *  3. Assign each diver to a group led by the best-qualified available leader,
 *     respecting max group size (4) and depth constraints from the rules.
 *  4. Remaining autonomous-capable divers are paired into buddy teams.
 *
 * @author  ClubCEP.eu
 *
 * @see     \App\Http\Controllers\DiveGroupController  — consumes proposals
 * @see     \App\Models\DiveGroupRule                   — federation rule definitions
 */

namespace App\Services;

use App\Models\DiveGroupRule;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Collection;

class DiveGroupProposalService
{
    /** Maximum divers per group (leader excluded). */
    private const MAX_DIVERS_PER_GROUP = 3;

    /**
     * Propose dive groups for an event based on confirmed registrations and rules.
     *
     * @return array{groups: array, unassigned: array, warnings: array}
     */
    public function propose(Event $event, ?int $maxDepth = null): array
    {
        $event->load(['registrations.user.certificationLevels.federation', 'registrations.user.detail', 'diveSite']);

        $maxDepth = $maxDepth ?? $event->diveSite?->max_depth ?? 20;
        $rules = DiveGroupRule::active()->get();
        $participants = $event->registrations->where('status', 'confirmed');

        // Build participant profiles with rank info
        $profiles = $participants->map(fn ($reg): array => $this->buildProfile($reg->user))->keyBy('user_id');

        // Split into potential leaders (instructors + high-rank divers) and regular divers
        $leaders = $profiles->filter(fn ($p) => $p['can_lead'])->sortByDesc('rank');
        $divers = $profiles->filter(fn ($p): bool => ! $p['can_lead'])->sortBy('rank');

        $groups = [];
        $assigned = [];
        $warnings = [];
        $groupNum = 1;

        // Phase 1: Supervised groups — assign weakest divers first to strongest
        // leaders, so the most qualified instructors supervise beginners
        foreach ($leaders as $leaderId => $leader) {
            if (isset($assigned[$leaderId])) {
                continue;
            }

            $groupDivers = [];

            foreach ($divers as $diverId => $diver) {
                if (isset($assigned[$diverId])) {
                    continue;
                }
                if (count($groupDivers) >= self::MAX_DIVERS_PER_GROUP) {
                    break;
                }

                // Check if this leader can supervise this diver at the planned depth
                $rule = $this->findRule($rules, $diver, $leader, $maxDepth);
                if ($rule) {
                    $groupDivers[$diverId] = $diver;
                    $assigned[$diverId] = true;
                }
            }

            if ($groupDivers !== []) {
                $assigned[$leaderId] = true;
                // Instructor-led = supervised; strong diver-led (GP/N4) = autonomous
                $mode = $leader['category'] === 'instructor' ? 'supervised' : 'autonomous';
                $effectiveDepth = $this->effectiveDepth($rules, $groupDivers, $leader, $maxDepth);

                $groups[] = [
                    'name' => __('Group').' '.$groupNum++,
                    'dive_mode' => $mode,
                    'planned_depth' => $effectiveDepth,
                    'leader' => $leader,
                    'members' => array_values($groupDivers),
                ];
            }
        }

        // Phase 2: Autonomous groups — remaining N2+/PA20+ divers (rank >= 30)
        // grouped by 2-3, strongest as leader, respecting autonomous depth limits
        $remaining = $profiles->filter(fn ($p): bool => ! isset($assigned[$p['user_id']]) && $p['rank'] >= 30)
            ->sortByDesc('rank')->values()->all();

        $i = 0;
        while ($i < count($remaining)) {
            if (isset($assigned[$remaining[$i]['user_id']])) {
                $i++;

                continue;
            }

            $leader = $remaining[$i];
            $buddies = [];

            // Grab up to 2 more buddies (max group of 3 for autonomous)
            for ($j = $i + 1; $j < count($remaining) && count($buddies) < 2; $j++) {
                if (isset($assigned[$remaining[$j]['user_id']])) {
                    continue;
                }
                $buddies[] = $remaining[$j];
            }

            if ($buddies === []) {
                $i++;

                continue;
            }

            $assigned[$leader['user_id']] = true;
            foreach ($buddies as $b) {
                $assigned[$b['user_id']] = true;
            }

            $minRank = min($leader['rank'], ...array_column($buddies, 'rank'));
            $effectiveDepth = min($maxDepth, $this->autonomousMaxDepth($minRank));

            $groups[] = [
                'name' => __('Group').' '.$groupNum++,
                'dive_mode' => 'autonomous',
                'planned_depth' => $effectiveDepth,
                'leader' => $leader,
                'members' => $buddies,
            ];
            $i++;
        }

        // Collect unassigned
        $unassigned = $profiles->filter(fn ($p): bool => ! isset($assigned[$p['user_id']]))->values()->all();

        if (! empty($unassigned)) {
            $names = implode(', ', array_column($unassigned, 'name'));
            $warnings[] = __(':count participant(s) could not be assigned: :names', [
                'count' => count($unassigned),
                'names' => $names,
            ]);
        }

        return [
            'groups' => $groups,
            'unassigned' => $unassigned,
            'warnings' => $warnings,
            'max_depth' => $maxDepth,
        ];
    }

    /**
     * Build a profile array for a user with their certification info.
     */
    private function buildProfile(User $user): array
    {
        $cert = $user->certificationLevels
            ->where('category', '!=', 'specialty')
            ->sortByDesc('rank')
            ->first();

        $rank = $cert?->rank ?? 0;
        $category = $cert?->category ?? 'none';
        $isInstructor = $category === 'instructor' || $user->detail?->active_instructor;

        return [
            'user_id' => $user->id,
            'name' => ($user->detail?->first_name ?? '').' '.($user->detail?->last_name ?? ''),
            'rank' => $rank,
            'cert_code' => $cert?->code ?? __('No cert'),
            'federation' => $cert?->federation?->acronym ?? '',
            'category' => $isInstructor ? 'instructor' : $category,
            'can_lead' => $rank >= 70 || $isInstructor, // GP/N4+ (rank 70+) or instructor can lead supervised groups
        ];
    }

    /**
     * Find the best matching rule for a diver-leader pair at a given depth.
     */
    private function findRule(Collection $rules, array $diver, array $leader, int $maxDepth): ?DiveGroupRule
    {
        return $rules
            ->filter(function (DiveGroupRule $rule) use ($diver, $leader, $maxDepth): bool {
                if (! $rule->matchesDiver($diver['rank'])) {
                    return false;
                }
                if (! $rule->leaderSatisfied($leader['rank'], $leader['category'])) {
                    return false;
                }

                return ! ($rule->max_depth && $rule->max_depth < min($maxDepth, 20));
            })
            ->sortByDesc('max_depth')
            ->first();
    }

    /**
     * Calculate the effective max depth for a group based on the weakest member's rules.
     */
    private function effectiveDepth(Collection $rules, array $divers, array $leader, int $siteMax): int
    {
        $minAllowed = $siteMax;

        foreach ($divers as $diver) {
            $rule = $this->findRule($rules, $diver, $leader, $siteMax);
            if ($rule && $rule->max_depth) {
                $minAllowed = min($minAllowed, $rule->max_depth);
            }
        }

        return $minAllowed;
    }

    /**
     * Max autonomous depth based on rank (simplified FFESSM table).
     * rank 60+ = N4/GP → 60m, rank 50+ = N3/PA40 → 40m, rank 30+ = N2/PA20 → 20m.
     */
    private function autonomousMaxDepth(int $rank): int
    {
        if ($rank >= 60) {
            return 60;
        }
        if ($rank >= 50) {
            return 40;
        }
        if ($rank >= 30) {
            return 20;
        }

        return 12;
    }
}
