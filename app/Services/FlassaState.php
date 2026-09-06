<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The three mutually exclusive states of the FLASSA licence component for a
 * membership order. `NotApplicable` means the component is absent from the
 * order entirely (sympathisant); `IncludedFree` means it is present at 0,00 €
 * (member under 18); `Required` means it is billed at its full amount.
 *
 * See .kiro/specs/membership-dues-calculation/requirements.md (R5, R6, R7, R8).
 */
enum FlassaState: string
{
    case Required = 'required';
    case IncludedFree = 'included_free';
    case NotApplicable = 'not_applicable';

    /** Whether the FLASSA component exists on the order at all (R8). */
    public function isApplicable(): bool
    {
        return $this !== self::NotApplicable;
    }

    /** Whether the component appears in the communication (R5 excludes it). */
    public function appearsInCommunication(): bool
    {
        return $this->isApplicable();
    }
}
