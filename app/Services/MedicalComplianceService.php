<?php

/**
 * Medical certificate compliance — per-federation evaluation.
 *
 * Computes expiry per federation independently. A member is compliant
 * if ANY active federation considers the cert valid. Warnings shown
 * for federations where it's expired.
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
     * Evaluate a certificate against each active federation independently.
     * Stores the most generous expiry (latest date) as the document expiry,
     * plus per-federation breakdown in compliance_notes.
     */
    public function evaluateCertificate(Document $document): void
    {
        $user = $document->user;
        $age = $user->detail?->date_of_birth?->age;
        $issueDate = $document->date_established ?? $document->created_at;

        $activeFeds = Federation::where('visibility', 'active')
            ->whereHas('complianceRules')
            ->get();

        $perFed = [];

        foreach ($activeFeds as $fed) {
            $rules = MedicalComplianceRule::where('federation_id', $fed->id)
                ->when($age !== null, fn ($q) => $q->where('age_bracket_low', '<=', $age)->where('age_bracket_high', '>=', $age))
                ->get();

            if ($rules->isEmpty()) {
                continue;
            }

            $expiry = $this->computeExpiry($fed, $rules, $issueDate, $age);
            $perFed[$fed->acronym] = $expiry;
        }

        if (empty($perFed)) {
            $document->update([
                'expiry_date' => $issueDate->copy()->addMonths(12),
                'is_compliant' => null,
                'compliance_notes' => 'No matching rules — default 12 months.',
            ]);

            return;
        }

        // Document expiry = latest (most generous) across all federations
        $latestExpiry = collect($perFed)->max();
        $notes = collect($perFed)->map(fn ($exp, $fed) => "{$fed}: {$exp->format('d/m/Y')}")->implode(' | ');

        $document->update([
            'expiry_date' => $latestExpiry,
            'is_compliant' => $latestExpiry->isFuture(),
            'compliance_notes' => $notes,
        ]);

        // Supersede previous current medical certs
        Document::where('user_id', $document->user_id)
            ->where('category', 'medical')
            ->where('is_current', true)
            ->where('id', '!=', $document->id)
            ->update(['is_current' => false, 'superseded_by' => $document->id]);
    }

    private function computeExpiry(Federation $fed, $rules, Carbon $issueDate, ?int $age): Carbon
    {
        $minMonths = $rules->min('validity_months');
        $expiry = $issueDate->copy()->addMonths($minMonths);

        // LIFRAS calendar rule
        if ($fed->acronym === 'LIFRAS') {
            $year = $issueDate->year;
            $expiry = $issueDate->month >= 9
                ? Carbon::create($year + 2, 1, 31)
                : Carbon::create($year + 1, 1, 31);
        }

        // FLASSA calendar rule
        if ($fed->acronym === 'FLASSA' && $age !== null) {
            $year = $issueDate->year;
            $beforeSep = $issueDate->month < 9;
            if ($age >= 18 && $age <= 45) {
                $expiry = $beforeSep
                    ? Carbon::create($year + 1, 12, 31)
                    : Carbon::create($year + 2, 12, 31);
            } else {
                $expiry = $beforeSep
                    ? Carbon::create($year, 12, 31)
                    : Carbon::create($year + 1, 12, 31);
            }
        }

        // FFESSM: straight day-to-day (issue + validity_months)
        // Already handled by the default $minMonths calculation above

        return $expiry;
    }

    /**
     * Compliant if ANY active federation considers the cert valid.
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
     * Get compliance status with per-federation breakdown.
     */
    public function getStatus(User $user, ?Carbon $atDate = null): array
    {
        $date = $atDate ?? now();

        $cert = $user->documents()
            ->where('category', 'medical')
            ->where('is_current', true)
            ->first();

        if (! $cert) {
            return ['status' => 'missing', 'badge' => 'danger', 'label' => 'No Certificate', 'days' => null, 'cert' => null, 'warnings' => []];
        }

        if (! $cert->expiry_date || $cert->expiry_date <= $date) {
            return ['status' => 'expired', 'badge' => 'danger', 'label' => 'Expired', 'days' => $cert->daysUntilExpiry(), 'cert' => $cert, 'warnings' => []];
        }

        $days = (int) $date->diffInDays($cert->expiry_date, false);

        // Per-federation warnings from compliance_notes
        $warnings = [];
        if ($cert->compliance_notes) {
            foreach (explode(' | ', $cert->compliance_notes) as $part) {
                if (preg_match('/^(\w+): (\d{2}\/\d{2}\/\d{4})$/', $part, $m)) {
                    $fedExpiry = Carbon::createFromFormat('d/m/Y', $m[2]);
                    if ($fedExpiry <= $date) {
                        $warnings[] = __('Expired per :fed rules', ['fed' => $m[1]]);
                    }
                }
            }
        }

        if ($days <= 30) {
            return ['status' => 'expiring', 'badge' => 'warning', 'label' => "Expires in {$days}d", 'days' => $days, 'cert' => $cert, 'warnings' => $warnings];
        }

        return ['status' => 'compliant', 'badge' => 'success', 'label' => 'Compliant', 'days' => $days, 'cert' => $cert, 'warnings' => $warnings];
    }
}
