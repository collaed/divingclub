<?php

namespace App\Services\Homogeneity;

use App\Enums\AssessmentStatus;
use App\Enums\HomogeneityFactorType;

/**
 * Evaluates how well-matched a group of divers is for diving together.
 *
 * Design decisions (from review):
 * - No RED status in this layer — only GREEN/ORANGE. Red requires explicit club policy.
 * - Edge cases protected: 0 divers → orange/empty, 1 diver → green/100.
 * - Pair penalties scaled by pair count to avoid punishing larger groups disproportionately.
 * - Factor type families are capped to avoid opaque double-penalty stacking.
 */
class HomogeneityAssessmentService
{
    /** @var array<string, int> Cap per factor-type family prefix */
    private const FAMILY_CAPS = [
        'air' => -30,
        'ease' => -30,
        'intent' => -25,
    ];

    public function __construct(
        private HomogeneityPolicy $policy = new HomogeneityPolicy,
    ) {}

    /**
     * @param  array  $divers  Array of diver profile arrays with keys:
     *                         airConsumption (float 0-1), easeLevel (float 0-1), primaryIntent (string),
     *                         isPhotographer (bool), certRank (int), totalDives (int), lastDiveWeeksAgo (int),
     *                         age (int), isFragile (bool)
     */
    public function assess(array $divers, DiveContext $ctx): HomogeneityAssessmentResult
    {
        // Fix #3: protect edge cases
        if (count($divers) === 0) {
            return new HomogeneityAssessmentResult(
                score: 0,
                status: AssessmentStatus::Orange,
                factors: [new HomogeneityFactor(
                    type: HomogeneityFactorType::Input,
                    scoreImpact: 0,
                    label: 'Aucun plongeur',
                    detail: "Aucune évaluation d'homogénéité n'est possible sans plongeur.",
                )],
                recommendations: ['Composer d\'abord une palanquée à évaluer.'],
            );
        }

        if (count($divers) === 1) {
            return new HomogeneityAssessmentResult(
                score: 100,
                status: AssessmentStatus::Green,
                factors: [],
                recommendations: [],
            );
        }

        $factors = [];
        $count = count($divers);

        // Fix #4: pair-count scaling factor (reference = 3 divers = 3 pairs)
        $pairCount = max(1, ($count * ($count - 1)) / 2);
        $pairScale = min(1.0, 3 / $pairCount);

        // Pair-wise comparisons
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $factors = array_merge($factors, $this->comparePair($divers[$i], $divers[$j], $ctx, $pairScale));
            }
        }

        // Group-level factors (not pair-scaled)
        $factors = array_merge($factors, $this->groupFactors($divers, $ctx));

        // Fix #1: cap factor families to avoid opaque double penalties
        $factors = $this->capFamilies($factors);

        $score = max(0, 100 + array_sum(array_map(fn (HomogeneityFactor $f) => $f->scoreImpact, $factors)));
        $recommendations = $this->buildRecommendations($factors);

        return new HomogeneityAssessmentResult(
            score: $score,
            status: $this->resolveStatus($score),
            factors: $factors,
            recommendations: $recommendations,
        );
    }

    /** @return HomogeneityFactor[] */
    private function comparePair(array $a, array $b, DiveContext $ctx, float $pairScale): array
    {
        $factors = [];
        $names = ($a['name'] ?? '?').' / '.($b['name'] ?? '?');

        // Air consumption gap
        $airGap = abs(($a['airConsumption'] ?? 0.5) - ($b['airConsumption'] ?? 0.5));
        if ($airGap >= $this->policy->airGapWarning) {
            $penalty = $airGap >= $this->policy->airGapStrong ? -15 : -8;
            $factors[] = new HomogeneityFactor(
                type: HomogeneityFactorType::Air,
                scoreImpact: (int) round($penalty * $pairScale),
                label: 'Écart consommation air',
                detail: 'Écart de '.round($airGap * 100)."% entre {$names}.",
                relatedDivers: [$a['name'] ?? '?', $b['name'] ?? '?'],
            );
        }

        // Ease/comfort level gap
        $easeGap = abs(($a['easeLevel'] ?? 0.5) - ($b['easeLevel'] ?? 0.5));
        if ($easeGap >= $this->policy->easeGapWarning) {
            $penalty = $easeGap >= $this->policy->easeGapStrong ? -12 : -6;
            $factors[] = new HomogeneityFactor(
                type: HomogeneityFactorType::Ease,
                scoreImpact: (int) round($penalty * $pairScale),
                label: 'Écart aisance',
                detail: "Écart d'aisance de ".round($easeGap * 100)."% entre {$names}.",
                relatedDivers: [$a['name'] ?? '?', $b['name'] ?? '?'],
            );
        }

        // Intent mismatch
        $intentA = $a['primaryIntent'] ?? 'exploration';
        $intentB = $b['primaryIntent'] ?? 'exploration';
        if ($intentA !== $intentB) {
            $factors[] = new HomogeneityFactor(
                type: HomogeneityFactorType::Intent,
                scoreImpact: (int) round(-5 * $pairScale),
                label: 'Objectifs différents',
                detail: "{$names}: {$intentA} vs {$intentB}.",
                relatedDivers: [$a['name'] ?? '?', $b['name'] ?? '?'],
            );
        }

        return $factors;
    }

    /** @return HomogeneityFactor[] */
    private function groupFactors(array $divers, DiveContext $ctx): array
    {
        $factors = [];

        // Deep air aggravation (group-level, renamed from deep_air_penalty)
        if ($ctx->plannedDepth >= 30) {
            $hasHighConsumer = false;
            foreach ($divers as $d) {
                if (($d['airConsumption'] ?? 0.5) >= 0.75) {
                    $hasHighConsumer = true;
                    break;
                }
            }
            if ($hasHighConsumer) {
                $factors[] = new HomogeneityFactor(
                    type: HomogeneityFactorType::DeepAirPenalty,
                    scoreImpact: -10,
                    label: 'Aggravation profondeur + consommation',
                    detail: "Plongée ≥30m avec au moins un gros consommateur d'air.",
                );
            }
        }

        // Cold + fragility
        if ($ctx->waterTempCelsius <= 10) {
            $fragileCount = count(array_filter($divers, fn ($d) => $d['isFragile'] ?? false));
            if ($fragileCount > 0) {
                $factors[] = new HomogeneityFactor(
                    type: HomogeneityFactorType::ColdFragility,
                    scoreImpact: -8 * $fragileCount,
                    label: 'Eau froide + plongeurs fragiles',
                    detail: "{$fragileCount} plongeur(s) fragile(s) en eau ≤10°C.",
                );
            }
        }

        // Junior load: too many inexperienced divers
        $juniorCount = count(array_filter($divers, fn ($d) => ($d['totalDives'] ?? 50) < 20));
        if ($juniorCount >= 2 && $juniorCount === count($divers)) {
            $factors[] = new HomogeneityFactor(
                type: HomogeneityFactorType::JuniorLoad,
                scoreImpact: -15,
                label: 'Palanquée entièrement junior',
                detail: 'Tous les plongeurs ont moins de 20 plongées.',
            );
        }

        // Intent dispersion (3+ different intents in group)
        $intents = array_unique(array_map(fn ($d) => $d['primaryIntent'] ?? 'exploration', $divers));
        if (count($intents) >= 3) {
            $factors[] = new HomogeneityFactor(
                type: HomogeneityFactorType::IntentDispersion,
                scoreImpact: -10,
                label: 'Objectifs trop dispersés',
                detail: count($intents).' objectifs différents dans la palanquée.',
            );
        }

        return $factors;
    }

    /**
     * Fix #1: Cap cumulative penalties per factor-type family.
     *
     * @param  HomogeneityFactor[]  $factors
     * @return HomogeneityFactor[]
     */
    private function capFamilies(array $factors): array
    {
        foreach (self::FAMILY_CAPS as $prefix => $minTotal) {
            $sum = 0;
            foreach ($factors as $f) {
                if (str_starts_with($f->type->value, $prefix)) {
                    $sum += $f->scoreImpact;
                }
            }

            if ($sum < $minTotal) {
                $ratio = $sum != 0 ? $minTotal / $sum : 1;
                foreach ($factors as $f) {
                    if (str_starts_with($f->type->value, $prefix)) {
                        $f->scoreImpact = (int) round($f->scoreImpact * $ratio);
                    }
                }
            }
        }

        return $factors;
    }

    /** @return string[] */
    private function buildRecommendations(array $factors): array
    {
        $recs = [];
        $familySums = [];

        foreach ($factors as $f) {
            $family = $f->type->value;
            $familySums[$family] = ($familySums[$family] ?? 0) + $f->scoreImpact;
        }

        // Intensity-aware recommendations (Fix #8)
        if (($familySums['air'] ?? 0) <= -20) {
            $recs[] = 'Recomposer la palanquée : écart de consommation trop important.';
        } elseif (($familySums['air'] ?? 0) <= -8) {
            $recs[] = 'Surveiller la consommation d\'air pendant la plongée.';
        }

        if (($familySums['ease'] ?? 0) <= -15) {
            $recs[] = 'Recomposer : niveaux d\'aisance trop hétérogènes.';
        } elseif (($familySums['ease'] ?? 0) <= -6) {
            $recs[] = 'Adapter le profil de plongée au plongeur le moins à l\'aise.';
        }

        if (isset($familySums['junior_load'])) {
            $recs[] = 'Ajouter un plongeur expérimenté à cette palanquée.';
        }

        if (isset($familySums['cold_fragility'])) {
            $recs[] = 'Réduire la durée de plongée ou vérifier l\'équipement thermique.';
        }

        if (isset($familySums['intent_dispersion']) || ($familySums['intent'] ?? 0) <= -10) {
            $recs[] = 'Regrouper les plongeurs par objectif similaire.';
        }

        return $recs;
    }

    /**
     * Fix #2: No red in this layer — only green/orange.
     * Red requires explicit club policy activation (future).
     */
    private function resolveStatus(int $score): AssessmentStatus
    {
        return $score <= $this->policy->orangeThreshold
            ? AssessmentStatus::Orange
            : AssessmentStatus::Green;
    }
}
