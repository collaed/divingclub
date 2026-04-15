<?php

namespace Tests\Unit;

use App\Enums\AssessmentStatus;
use App\Enums\HomogeneityFactorType;
use App\Services\Homogeneity\DiveContext;
use App\Services\Homogeneity\HomogeneityAssessmentService;
use App\Services\Homogeneity\HomogeneityPolicy;
use PHPUnit\Framework\TestCase;

/**
 * @group p2
 */
class HomogeneityAssessmentServiceTest extends TestCase
{
    private HomogeneityAssessmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HomogeneityAssessmentService;
    }

    // --- Edge cases ---

    public function test_empty_divers_returns_orange(): void
    {
        $result = $this->service->assess([], new DiveContext(20));

        $this->assertSame(AssessmentStatus::Orange, $result->status);
        $this->assertSame(0, $result->score);
    }

    public function test_single_diver_returns_green_100(): void
    {
        $result = $this->service->assess(
            [$this->diver('Alice')],
            new DiveContext(20)
        );

        $this->assertSame(100, $result->score);
        $this->assertSame(AssessmentStatus::Green, $result->status);
        $this->assertEmpty($result->factors);
    }

    // --- Homogeneous group ---

    public function test_identical_divers_score_100(): void
    {
        $divers = [
            $this->diver('Alice', airConsumption: 0.5, easeLevel: 0.5, intent: 'exploration', totalDives: 50),
            $this->diver('Bob', airConsumption: 0.5, easeLevel: 0.5, intent: 'exploration', totalDives: 50),
        ];

        $result = $this->service->assess($divers, new DiveContext(20));

        $this->assertSame(100, $result->score);
        $this->assertSame(AssessmentStatus::Green, $result->status);
    }

    // --- Air consumption gap ---

    public function test_large_air_gap_produces_penalty(): void
    {
        $divers = [
            $this->diver('Alice', airConsumption: 0.2, totalDives: 50),
            $this->diver('Bob', airConsumption: 0.7, totalDives: 50),
        ];

        $result = $this->service->assess($divers, new DiveContext(20));

        $airFactors = array_filter($result->factors, fn ($f) => $f->type === HomogeneityFactorType::Air);
        $this->assertNotEmpty($airFactors);
        $this->assertLessThan(100, $result->score);
    }

    // --- Ease level gap ---

    public function test_large_ease_gap_produces_penalty(): void
    {
        $divers = [
            $this->diver('Alice', easeLevel: 0.1, totalDives: 50),
            $this->diver('Bob', easeLevel: 0.6, totalDives: 50),
        ];

        $result = $this->service->assess($divers, new DiveContext(20));

        $easeFactors = array_filter($result->factors, fn ($f) => $f->type === HomogeneityFactorType::Ease);
        $this->assertNotEmpty($easeFactors);
    }

    // --- Intent mismatch ---

    public function test_different_intents_produce_penalty(): void
    {
        $divers = [
            $this->diver('Alice', intent: 'photography', totalDives: 50),
            $this->diver('Bob', intent: 'exploration', totalDives: 50),
        ];

        $result = $this->service->assess($divers, new DiveContext(20));

        $intentFactors = array_filter($result->factors, fn ($f) => $f->type === HomogeneityFactorType::Intent);
        $this->assertNotEmpty($intentFactors);
    }

    // --- Deep air penalty ---

    public function test_deep_dive_with_high_consumer_penalizes(): void
    {
        $divers = [
            $this->diver('Alice', airConsumption: 0.8, totalDives: 50),
            $this->diver('Bob', airConsumption: 0.3, totalDives: 50),
        ];

        $result = $this->service->assess($divers, new DiveContext(35));

        $deepFactors = array_filter($result->factors, fn ($f) => $f->type === HomogeneityFactorType::DeepAirPenalty);
        $this->assertNotEmpty($deepFactors);
    }

    public function test_shallow_dive_no_deep_air_penalty(): void
    {
        $divers = [
            $this->diver('Alice', airConsumption: 0.8, totalDives: 50),
            $this->diver('Bob', airConsumption: 0.3, totalDives: 50),
        ];

        $result = $this->service->assess($divers, new DiveContext(15));

        $deepFactors = array_filter($result->factors, fn ($f) => $f->type === HomogeneityFactorType::DeepAirPenalty);
        $this->assertEmpty($deepFactors);
    }

    // --- Cold fragility ---

    public function test_cold_water_with_fragile_diver_penalizes(): void
    {
        $divers = [
            $this->diver('Alice', isFragile: true, totalDives: 50),
            $this->diver('Bob', totalDives: 50),
        ];

        $result = $this->service->assess($divers, new DiveContext(20, waterTempCelsius: 8));

        $coldFactors = array_filter($result->factors, fn ($f) => $f->type === HomogeneityFactorType::ColdFragility);
        $this->assertNotEmpty($coldFactors);
    }

    public function test_warm_water_no_cold_penalty(): void
    {
        $divers = [
            $this->diver('Alice', isFragile: true, totalDives: 50),
            $this->diver('Bob', totalDives: 50),
        ];

        $result = $this->service->assess($divers, new DiveContext(20, waterTempCelsius: 20));

        $coldFactors = array_filter($result->factors, fn ($f) => $f->type === HomogeneityFactorType::ColdFragility);
        $this->assertEmpty($coldFactors);
    }

    // --- Junior load ---

    public function test_all_junior_divers_penalizes(): void
    {
        $divers = [
            $this->diver('Alice', totalDives: 5),
            $this->diver('Bob', totalDives: 10),
        ];

        $result = $this->service->assess($divers, new DiveContext(20));

        $juniorFactors = array_filter($result->factors, fn ($f) => $f->type === HomogeneityFactorType::JuniorLoad);
        $this->assertNotEmpty($juniorFactors);
    }

    public function test_mixed_experience_no_junior_penalty(): void
    {
        $divers = [
            $this->diver('Alice', totalDives: 5),
            $this->diver('Bob', totalDives: 100),
        ];

        $result = $this->service->assess($divers, new DiveContext(20));

        $juniorFactors = array_filter($result->factors, fn ($f) => $f->type === HomogeneityFactorType::JuniorLoad);
        $this->assertEmpty($juniorFactors);
    }

    // --- Intent dispersion ---

    public function test_three_different_intents_triggers_dispersion(): void
    {
        $divers = [
            $this->diver('Alice', intent: 'photography', totalDives: 50),
            $this->diver('Bob', intent: 'exploration', totalDives: 50),
            $this->diver('Carol', intent: 'training', totalDives: 50),
        ];

        $result = $this->service->assess($divers, new DiveContext(20));

        $dispersion = array_filter($result->factors, fn ($f) => $f->type === HomogeneityFactorType::IntentDispersion);
        $this->assertNotEmpty($dispersion);
    }

    // --- Family caps ---

    public function test_score_never_below_zero(): void
    {
        // Worst case: everything mismatched
        $divers = [
            $this->diver('Alice', airConsumption: 0.0, easeLevel: 0.0, intent: 'photo', totalDives: 2, isFragile: true),
            $this->diver('Bob', airConsumption: 1.0, easeLevel: 1.0, intent: 'training', totalDives: 3, isFragile: true),
            $this->diver('Carol', airConsumption: 0.9, easeLevel: 0.9, intent: 'exploration', totalDives: 1, isFragile: true),
        ];

        $result = $this->service->assess($divers, new DiveContext(40, waterTempCelsius: 5));

        $this->assertGreaterThanOrEqual(0, $result->score);
    }

    // --- Custom policy ---

    public function test_custom_policy_orange_threshold(): void
    {
        $policy = new HomogeneityPolicy(orangeThreshold: 95);
        $service = new HomogeneityAssessmentService($policy);

        $divers = [
            $this->diver('Alice', airConsumption: 0.3, totalDives: 50),
            $this->diver('Bob', airConsumption: 0.6, totalDives: 50),
        ];

        $result = $service->assess($divers, new DiveContext(20));

        // With a high threshold, even small penalties push to orange
        if ($result->score <= 95) {
            $this->assertSame(AssessmentStatus::Orange, $result->status);
        } else {
            $this->assertSame(AssessmentStatus::Green, $result->status);
        }
    }

    // --- Recommendations ---

    public function test_recommendations_generated_for_penalties(): void
    {
        $divers = [
            $this->diver('Alice', airConsumption: 0.1, easeLevel: 0.1, totalDives: 5),
            $this->diver('Bob', airConsumption: 0.9, easeLevel: 0.9, totalDives: 3),
        ];

        $result = $this->service->assess($divers, new DiveContext(20));

        $this->assertNotEmpty($result->recommendations);
    }

    // --- Helper ---

    private function diver(
        string $name,
        float $airConsumption = 0.5,
        float $easeLevel = 0.5,
        string $intent = 'exploration',
        int $totalDives = 50,
        bool $isFragile = false,
    ): array {
        return [
            'name' => $name,
            'airConsumption' => $airConsumption,
            'easeLevel' => $easeLevel,
            'primaryIntent' => $intent,
            'isPhotographer' => false,
            'certRank' => 30,
            'totalDives' => $totalDives,
            'lastDiveWeeksAgo' => 2,
            'age' => 35,
            'isFragile' => $isFragile,
        ];
    }
}
