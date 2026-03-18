<?php

namespace App\Services;

use App\Models\Document;
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
            ->when($userFederationIds, fn($q) => $q->whereIn('federation_id', $userFederationIds))
            ->when($age !== null, fn($q) => $q->where('age_bracket_low', '<=', $age)->where('age_bracket_high', '>=', $age))
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
     * Check if a user is medically compliant (has a current, non-expired medical cert).
     */
    public function isCompliant(User $user): bool
    {
        return $user->documents()
            ->where('category', 'medical')
            ->where('is_current', true)
            ->where(fn($q) => $q->whereNull('expiry_date')->orWhere('expiry_date', '>', now()))
            ->exists();
    }

    /**
     * Get compliance status details for a user.
     */
    public function getStatus(User $user): array
    {
        $cert = $user->documents()
            ->where('category', 'medical')
            ->where('is_current', true)
            ->first();

        if (!$cert) {
            return ['status' => 'missing', 'badge' => 'danger', 'label' => 'No Certificate', 'days' => null, 'cert' => null];
        }

        if ($cert->isExpired()) {
            return ['status' => 'expired', 'badge' => 'danger', 'label' => 'Expired', 'days' => $cert->daysUntilExpiry(), 'cert' => $cert];
        }

        $days = $cert->daysUntilExpiry();
        if ($days !== null && $days <= 30) {
            return ['status' => 'expiring', 'badge' => 'warning', 'label' => "Expires in {$days}d", 'days' => $days, 'cert' => $cert];
        }

        return ['status' => 'compliant', 'badge' => 'success', 'label' => 'Compliant', 'days' => $days, 'cert' => $cert];
    }
}
