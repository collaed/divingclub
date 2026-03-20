<?php

namespace App\Services\Homogeneity;

final class HomogeneityPolicy
{
    public function __construct(
        public float $airGapWarning = 0.25,
        public float $airGapStrong = 0.40,
        public float $easeGapWarning = 0.20,
        public float $easeGapStrong = 0.35,
        public float $fragileEaseThreshold = 0.70,
        public int $orangeThreshold = 79,
    ) {}
}
