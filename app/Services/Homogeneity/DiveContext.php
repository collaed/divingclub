<?php

declare(strict_types=1);

namespace App\Services\Homogeneity;

final class DiveContext
{
    public function __construct(
        public int $plannedDepth,
        public float $waterTempCelsius = 15.0,
        public string $environment = 'quarry', // quarry, sea, lake
    ) {}
}
