<?php

namespace App\Services;

use App\Models\MembershipFee;
use App\Models\MembershipFeeComponent;
use App\Models\PaymentExpected;
use App\Models\User;

class FeeCalculationService
{
    /**
     * Calculate membership dues: absolute amount per status + optional add-ons.
     */
    public function calculate(User $user, string $seasonYear, array $selectedOptionalSlugs = []): array
    {
        $status = $user->status;

        // Look up the absolute fee for this status and year
        $fee = MembershipFee::where('season_year', $seasonYear)
            ->where('status_id', $status?->id)
            ->first();

        $baseFee = $fee?->amount ?? 0;

        // Optional add-on components (insurance, double affiliation, etc.)
        $optionals = MembershipFeeComponent::where('is_optional', true)
            ->whereIn('slug', $selectedOptionalSlugs)->get();
        $optionalTotal = $optionals->sum('amount');

        $total = round($baseFee + $optionalTotal, 2);

        $components = ['membership' => $baseFee, 'status' => $status?->name ?? '—', 'label' => $fee?->label ?? ''];
        foreach ($optionals as $opt) {
            $components[$opt->slug] = $opt->amount;
        }

        return [
            'amount_due' => $total,
            'components' => $components,
            'communication' => $this->buildCommunication($user, $seasonYear, $selectedOptionalSlugs),
        ];
    }

    public function createPaymentExpected(User $user, string $seasonYear, array $selectedOptionalSlugs = []): PaymentExpected
    {
        $calc = $this->calculate($user, $seasonYear, $selectedOptionalSlugs);

        return PaymentExpected::updateOrCreate(
            ['user_id' => $user->id, 'type' => 'membership', 'season_year' => $seasonYear],
            ['amount_due' => $calc['amount_due'], 'communication' => $calc['communication'], 'components' => $calc['components']]
        );
    }

    public function buildCommunication(User $user, string $seasonYear, array $optionals): string
    {
        $name = strtoupper(trim(($user->detail?->last_name ?? '') . ' ' . ($user->detail?->first_name ?? '')));
        $opts = $optionals ? '+' . implode('+', $optionals) : '';
        return "CEP-{$seasonYear}-{$user->id}-{$name}{$opts}";
    }

    /**
     * Build a human-readable breakdown for display.
     */
    public function breakdown(User $user, string $seasonYear, array $selectedOptionalSlugs = []): array
    {
        $calc = $this->calculate($user, $seasonYear, $selectedOptionalSlugs);
        $lines = [];
        $lines[] = ['label' => __('Membership') . ' (' . ($calc['components']['status'] ?? '') . ')', 'amount' => $calc['components']['membership']];
        foreach (MembershipFeeComponent::where('is_optional', true)->whereIn('slug', $selectedOptionalSlugs)->get() as $opt) {
            $lines[] = ['label' => $opt->name, 'amount' => $opt->amount];
        }
        $lines[] = ['label' => __('Total'), 'amount' => $calc['amount_due'], 'bold' => true];
        return $lines;
    }
}
