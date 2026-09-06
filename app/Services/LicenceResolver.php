<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;

/**
 * Pure domain rules that derive a membership order's federation components from
 * the chosen cotisation status and the member's age at the shared prise-de-
 * licence anchor. No database, no HTTP — fully unit-testable.
 *
 * Implements requirements R1 (FFESSM age-band derivation), R2 (assurance
 * requires a licence), R3 (applicability matrix), R5/R6/R7 (FLASSA three-state)
 * and R8 (two distinct zero states). See
 * .kiro/specs/membership-dues-calculation/{requirements,design}.md.
 */
class LicenceResolver
{
    /** FFESSM licence slug for a member with no federation licence (sympathisant). */
    public const FFESSM_NONE = 'lic_aucune';

    public const FFESSM_ADULTE = 'lic_adulte';

    public const FFESSM_JEUNE = 'lic_jeune';

    public const FFESSM_ENFANT = 'lic_enfant';

    /** Federation age-band cutoffs (years, at the anchor). */
    private const JEUNE_MIN_AGE = 12;

    private const ADULTE_MIN_AGE = 16;

    /** FLASSA is included free below this age at the anchor (R6/R7). */
    private const FLASSA_ADULT_AGE = 18;

    /**
     * Cotisation slugs that carry no federation licence and no FLASSA (R5).
     * Kept as a set so the calculator can pass any sympathisant-style slug.
     *
     * @var array<int, string>
     */
    private const NON_LICENSED_COTISATIONS = ['coti_sympathisant', 'sympathisant'];

    /**
     * Derive the FFESSM licence, FLASSA state and assurance gate for an order.
     *
     * @param  string  $cotisationSlug  the chosen Group 1 cotisation (or status slug)
     * @param  Carbon|null  $dateOfBirth  member DOB; null is treated as adult (full charge)
     * @param  Carbon  $anchor  the shared prise-de-licence anchor date
     */
    public function resolve(string $cotisationSlug, ?Carbon $dateOfBirth, Carbon $anchor): LicenceDerivation
    {
        if ($this->isNonLicensed($cotisationSlug)) {
            // Sympathisant: no FFESSM licence, FLASSA absent, assurance forced off.
            return new LicenceDerivation(self::FFESSM_NONE, FlassaState::NotApplicable, false);
        }

        $ageAtAnchor = $this->ageAtAnchor($dateOfBirth, $anchor);

        return new LicenceDerivation(
            $this->ffessmBand($ageAtAnchor),
            $this->flassaState($ageAtAnchor),
            true,
        );
    }

    /**
     * FFESSM band from age at the anchor (R1): Enfant < 12, Jeune 12 to < 16,
     * Adulte 16+. A null age (unknown DOB) is treated as adult.
     */
    public function ffessmBand(?int $ageAtAnchor): string
    {
        if ($ageAtAnchor === null || $ageAtAnchor >= self::ADULTE_MIN_AGE) {
            return self::FFESSM_ADULTE;
        }

        if ($ageAtAnchor >= self::JEUNE_MIN_AGE) {
            return self::FFESSM_JEUNE;
        }

        return self::FFESSM_ENFANT;
    }

    /**
     * FLASSA state for a licensed member (R6/R7): included free below 18 at the
     * anchor, required at 18+. A null age (unknown DOB) is treated as adult, so
     * FLASSA is required (full charge) — matching FeeCalculationService.
     */
    public function flassaState(?int $ageAtAnchor): FlassaState
    {
        if ($ageAtAnchor !== null && $ageAtAnchor < self::FLASSA_ADULT_AGE) {
            return FlassaState::IncludedFree;
        }

        return FlassaState::Required;
    }

    private function isNonLicensed(string $cotisationSlug): bool
    {
        return in_array($cotisationSlug, self::NON_LICENSED_COTISATIONS, true);
    }

    /** Whole-year age at the anchor, or null when DOB is unknown. */
    private function ageAtAnchor(?Carbon $dateOfBirth, Carbon $anchor): ?int
    {
        if (! $dateOfBirth instanceof Carbon) {
            return null;
        }

        return (int) $dateOfBirth->diffInYears($anchor);
    }
}
