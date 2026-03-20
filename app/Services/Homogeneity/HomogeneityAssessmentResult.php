<?php

namespace App\Services\Homogeneity;

use App\Enums\AssessmentStatus;

final class HomogeneityAssessmentResult
{
    public function __construct(
        public int $score,
        public AssessmentStatus $status,
        /** @var HomogeneityFactor[] */
        public array $factors,
        /** @var string[] */
        public array $recommendations,
    ) {}
}
