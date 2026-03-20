<?php

namespace App\Services\Homogeneity;

use App\Enums\HomogeneityFactorType;

final class HomogeneityFactor
{
    public function __construct(
        public HomogeneityFactorType $type,
        public int $scoreImpact,
        public string $label,
        public string $detail,
        public array $relatedDivers = [],
    ) {}
}
