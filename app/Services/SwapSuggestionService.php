<?php

namespace App\Services;

use App\Models\Event;
use App\Services\Homogeneity\DiveContext;
use App\Services\Homogeneity\HomogeneityAssessmentService;

/**
 * Suggests member swaps between dive groups to improve overall homogeneity.
 *
 * Algorithm: for each pair of groups, try swapping each non-leader member
 * and keep the swap that yields the best combined score improvement.
 * Greedy single-pass — O(g² × m²) where g = groups, m = members per group.
 */
class SwapSuggestionService
{
    private HomogeneityAssessmentService $assessor;

    public function __construct(
        private int $maxSuggestions = 5,
        private int $minImprovement = 3,
    ) {
        $this->assessor = new HomogeneityAssessmentService;
    }

    /**
     * @return array{suggestions: array, current_scores: array}
     */
    public function suggest(Event $event): array
    {
        $event->load([
            'diveGroups.members.user.certificationLevels.federation',
            'diveGroups.members.user.detail',
            'diveSite',
        ]);

        $groups = $event->diveGroups;
        if ($groups->count() < 2) {
            return ['suggestions' => [], 'current_scores' => []];
        }

        $ctx = new DiveContext(
            plannedDepth: $event->diveSite?->max_depth ?? 20,
            waterTempCelsius: $event->diveSite?->water_temperature ?? 15.0,
        );

        // Build profiles per group
        $groupProfiles = [];
        $currentScores = [];
        foreach ($groups as $group) {
            $profiles = $group->members->map(fn (object $m): array => $this->buildProfile($m))->all();
            $groupProfiles[$group->id] = $profiles;
            $result = $this->assessor->assess($profiles, $ctx);
            $currentScores[$group->id] = [
                'name' => $group->name,
                'score' => $result->score,
                'status' => $result->status->value,
            ];
        }

        $suggestions = [];
        $groupIds = array_keys($groupProfiles);
        $counter = count($groupIds);

        for ($gi = 0; $gi < $counter; $gi++) {
            for ($gj = $gi + 1; $gj < count($groupIds); $gj++) {
                $gidA = $groupIds[$gi];
                $gidB = $groupIds[$gj];
                $profilesA = $groupProfiles[$gidA];
                $profilesB = $groupProfiles[$gidB];
                $baseSum = $currentScores[$gidA]['score'] + $currentScores[$gidB]['score'];

                $bestSwap = null;
                $bestGain = 0;

                foreach ($profilesA as $ia => $pa) {
                    if ($pa['role'] === 'leader') {
                        continue;
                    }
                    foreach ($profilesB as $ib => $pb) {
                        if ($pb['role'] === 'leader') {
                            continue;
                        }

                        // Try swap
                        $newA = $profilesA;
                        $newB = $profilesB;
                        $newA[$ia] = $pb;
                        $newB[$ib] = $pa;

                        $scoreA = $this->assessor->assess(array_values($newA), $ctx)->score;
                        $scoreB = $this->assessor->assess(array_values($newB), $ctx)->score;
                        $gain = ($scoreA + $scoreB) - $baseSum;

                        if ($gain > $bestGain) {
                            $bestGain = $gain;
                            $bestSwap = [
                                'from_group' => $currentScores[$gidA]['name'],
                                'to_group' => $currentScores[$gidB]['name'],
                                'member_a' => $pa['name'],
                                'member_a_id' => $pa['user_id'],
                                'member_b' => $pb['name'],
                                'member_b_id' => $pb['user_id'],
                                'gain' => $gain,
                                'new_score_a' => $scoreA,
                                'new_score_b' => $scoreB,
                            ];
                        }
                    }
                }

                if ($bestSwap && $bestSwap['gain'] >= $this->minImprovement) {
                    $suggestions[] = $bestSwap;
                }
            }
        }

        // Sort by gain descending, limit
        usort($suggestions, fn (array $a, array $b): int => $b['gain'] <=> $a['gain']);
        $suggestions = array_slice($suggestions, 0, $this->maxSuggestions);

        return [
            'suggestions' => $suggestions,
            'current_scores' => array_values($currentScores),
        ];
    }

    private function buildProfile(object $member): array
    {
        $user = $member->user;
        $detail = $user->detail;
        $cert = $user->certificationLevels
            ->where('category', '!=', 'specialty')
            ->sortByDesc('rank')
            ->first();

        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'role' => $member->role,
            'airConsumption' => (float) ($detail?->air_consumption ?? 0.5),
            'easeLevel' => (float) ($detail?->ease_level ?? 0.5),
            'primaryIntent' => $detail?->primary_intent ?? 'exploration',
            'isPhotographer' => (bool) ($detail?->is_photographer ?? false),
            'certRank' => $cert?->rank ?? 0,
            'totalDives' => (int) ($detail?->total_dives ?? $detail?->dive_count ?? 50),
            'lastDiveWeeksAgo' => $detail?->last_dive_date
                ? (int) now()->diffInWeeks($detail->last_dive_date)
                : 12,
            'age' => $detail?->date_of_birth?->age ?? 30,
            'isFragile' => ($detail?->date_of_birth?->age ?? 30) >= 65 || ($detail?->date_of_birth?->age ?? 30) < 16,
        ];
    }
}
