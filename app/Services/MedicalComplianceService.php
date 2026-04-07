<?php

/**
 * Medical certificate compliance evaluation and status checking.
 *
 * Evaluates uploaded medical certificates against federation-specific rules
 * (FFESSM age brackets, LIFRAS calendar-based validity). Determines expiry
 * dates, supersedes previous certificates, and provides compliance status
 * for dive registration gates.
 *
 * @author ClubCEP.eu
 */

namespace App\Services;

use App\Models\Document;
use App\Models\Federation;
use App\Models\MedicalComplianceRule;
use App\Models\User;
use Carbon\Carbon;

class MedicalComplianceService
{
    /**
     * Evaluate a newly uploaded medical certificate against all matching rules.
     * Sets expiry_date = issue_date + minimum validity_months from matching rules.
     * Supersedes previous current medical cert.
     */
    public function evaluateCertificate(Document $document): void
    {
        $user = $document->user;
        $age = $user->detail?->date_of_birth?->age;
        $issueDate = $document->date_established ?? $document->created_at;

        // Find all matching rules (by federation membership + age bracket)
        $userFederationIds = $user->licences()->pluck('federation_id')->toArray();

        $rules = MedicalComplianceRule::query()
            ->when($userFederationIds, fn ($q) => $q->whereIn('federation_id', $userFederationIds))
            ->when($age !== null, fn ($q) => $q->where('age_bracket_low', '<=', $age)->where('age_bracket_high', '>=', $age))
            ->get();

        if ($rules->isEmpty()) {
            // No matching rules — use default 12 months, flag for review
            $document->update([
                'expiry_date' => $issueDate->copy()->addMonths(12),
                'is_compliant' => null,
                'compliance_notes' => 'No matching compliance rules found — using default 12 months. Bureau review needed.',
            ]);

            return;
        }

        // Use minimum validity_months from all matching rules (most restrictive)
        $minMonths = $rules->min('validity_months');
        $expiryDate = $issueDate->copy()->addMonths($minMonths);

        // LIFRAS calendar-based rule (MIL 2026 §1.5.1):
        // Jan 1 - Aug 31 of year N → valid until Jan 31 of N+1
        // Sep 1 - Dec 31 of year N → valid until Jan 31 of N+2
        $lifrasId = 2; // LIFRAS federation_id
        if ($rules->contains('federation_id', $lifrasId)) {
            $year = $issueDate->year;
            $expiryDate = $issueDate->month >= 9
                ? Carbon::create($year + 2, 1, 31)
                : Carbon::create($year + 1, 1, 31);
        }

        // FLASSA calendar-based rule:
        // Age 18-45: cert Jan-Aug → valid until Dec 31 of Y+1; cert Sep-Dec → valid until Dec 31 of Y+2
        // Age <18 or >45: cert Jan-Aug → valid until Dec 31 of Y; cert Sep-Dec → valid until Dec 31 of Y+1
        $flassaId = Federation::where('acronym', 'FLASSA')->value('id');
        if ($flassaId && $rules->contains('federation_id', $flassaId) && $age !== null) {
            $year = $issueDate->year;
            $beforeSep = $issueDate->month < 9;
            if ($age >= 18 && $age <= 45) {
                $expiryDate = $beforeSep
                    ? Carbon::create($year + 1, 12, 31)
                    : Carbon::create($year + 2, 12, 31);
            } else {
                $expiryDate = $beforeSep
                    ? Carbon::create($year, 12, 31)
                    : Carbon::create($year + 1, 12, 31);
            }
        }

        $document->update([
            'expiry_date' => $expiryDate,
            'is_compliant' => $expiryDate->isFuture(),
            'compliance_notes' => "Evaluated against {$rules->count()} rule(s). Validity: {$minMonths} months.",
        ]);

        // Supersede previous current medical certs
        Document::where('user_id', $document->user_id)
            ->where('category', 'medical')
            ->where('is_current', true)
            ->where('id', '!=', $document->id)
            ->update(['is_current' => false, 'superseded_by' => $document->id]);
    }

    /**
     * Check if a user is medically compliant at a given date (defaults to now).
     */
    public function isCompliant(User $user, ?Carbon $atDate = null): bool
    {
        $date = $atDate ?? now();

        return $user->documents()
            ->where('category', 'medical')
            ->where('is_current', true)
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhere('expiry_date', '>', $date))
            ->exists();
    }

    /**
     * Get compliance status details for a user, optionally at a specific date.
     */
    public function getStatus(User $user, ?Carbon $atDate = null): array
    {
        $date = $atDate ?? now();

        $cert = $user->documents()
            ->where('category', 'medical')
            ->where('is_current', true)
            ->first();

        if (! $cert) {
            return ['status' => 'missing', 'badge' => 'danger', 'label' => 'No Certificate', 'days' => null, 'cert' => null];
        }

        if (! $cert->expiry_date || $cert->expiry_date <= $date) {
            return ['status' => 'expired', 'badge' => 'danger', 'label' => 'Expired', 'days' => $cert->daysUntilExpiry(), 'cert' => $cert];
        }

        $days = (int) $date->diffInDays($cert->expiry_date, false);
        if ($days <= 30) {
            return ['status' => 'expiring', 'badge' => 'warning', 'label' => "Expires in {$days}d", 'days' => $days, 'cert' => $cert];
        }

        return ['status' => 'compliant', 'badge' => 'success', 'label' => 'Compliant', 'days' => $days, 'cert' => $cert];
    }
}
