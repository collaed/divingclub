<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The derived, read-only outcome of resolving a membership order's federation
 * components from the chosen cotisation and the member's age at the shared
 * prise-de-licence anchor.
 *
 * See .kiro/specs/membership-dues-calculation/design.md (§4) — every field is
 * a pure function of (cotisation slug, date_of_birth, anchor date).
 */
final readonly class LicenceDerivation
{
    public function __construct(
        public string $ffessmSlug,
        public FlassaState $flassaState,
        public bool $assuranceAllowed,
    ) {}
}
