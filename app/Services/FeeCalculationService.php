<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MembershipFee;
use App\Models\MembershipFeeComponent;
use App\Models\PaymentExpected;
use App\Models\Season;
use App\Models\ThemeSetting;
use App\Models\User;
use Carbon\Carbon;

class FeeCalculationService
{
    /**
     * Calculate membership dues: absolute amount per status + optional add-ons.
     * The club-retained CEP membership base is tapered by the season's schedule
     * (percentage rounded up to the higher euro); optional components are not.
     *
     * @param  string[]  $selectedOptionalSlugs
     * @return array{amount_due: float, components: array<string, mixed>, communication: string}
     */
    public function calculate(User $user, string $seasonYear, array $selectedOptionalSlugs = []): array
    {
        $status = $user->status;

        // Look up the absolute fee for this status and year
        $fee = MembershipFee::where('season_year', $seasonYear)
            ->where('status_id', $status?->id)
            ->first();

        $baseFee = (float) ($fee?->amount ?? 0);

        // Apply season-relative tapering to the club-retained membership base.
        $pct = $this->taperPercentage($seasonYear);
        $baseAfterTaper = $pct >= 100 ? $baseFee : (float) ceil($baseFee * $pct / 100);

        // Optional add-on components (insurance, double affiliation, etc.) — never tapered.
        $optionals = MembershipFeeComponent::where('is_optional', true)
            ->whereIn('slug', $selectedOptionalSlugs)->get();
        $optionalTotal = $optionals->sum('amount');

        $total = round($baseAfterTaper + $optionalTotal, 2);

        $components = ['membership' => $baseAfterTaper, 'status' => $status?->name ?? '—', 'label' => $fee?->label ?? ''];
        if ($pct < 100) {
            $components['membership_full'] = $baseFee;
            $components['taper_pct'] = $pct;
        }
        foreach ($optionals as $opt) {
            $components[$opt->slug] = $opt->amount;
        }

        return [
            'amount_due' => $total,
            'components' => $components,
            'communication' => $this->buildCommunication($user, $seasonYear, $selectedOptionalSlugs),
        ];
    }

    /**
     * Resolve the fee-taper percentage (0-100) for a season year against a
     * reference date. The reference date defaults to today, but can be pinned
     * via the `fee_taper_reference_date` setting (ISO date) to freeze the rate.
     */
    public function taperPercentage(string $seasonYear, ?Carbon $reference = null): int
    {
        $season = Season::where('year', $seasonYear)->first();
        if (! $season instanceof Season) {
            return 100;
        }

        if ($reference === null) {
            $pinned = ThemeSetting::get('fee_taper_reference_date');
            $reference = $pinned ? Carbon::parse((string) $pinned) : Carbon::today();
        }

        return $season->taperPercentage($reference);
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
        $name = strtoupper(trim(($user->detail?->last_name ?? '').' '.($user->detail?->first_name ?? '')));
        $opts = $optionals ? '+'.implode('+', $optionals) : '';
        $prefix = ThemeSetting::get('club_short_code', config('club.id', 'CLUB'));

        return "{$prefix}-{$seasonYear}-{$user->id}-{$name}{$opts}";
    }

    /**
     * Build a human-readable breakdown for display.
     *
     * @param  string[]  $selectedOptionalSlugs
     * @return array<int, array{label: string, amount: float, bold?: bool, muted?: bool}>
     */
    public function breakdown(User $user, string $seasonYear, array $selectedOptionalSlugs = []): array
    {
        $calc = $this->calculate($user, $seasonYear, $selectedOptionalSlugs);
        $lines = [];
        $lines[] = ['label' => __('Membership').' ('.($calc['components']['status'] ?? '').')', 'amount' => (float) $calc['components']['membership']];
        if (isset($calc['components']['taper_pct'])) {
            $lines[] = [
                'label' => __('Reduced rate (:pct%) — full :full€', [
                    'pct' => $calc['components']['taper_pct'],
                    'full' => number_format((float) $calc['components']['membership_full'], 0),
                ]),
                'amount' => 0.0,
                'muted' => true,
            ];
        }
        foreach (MembershipFeeComponent::where('is_optional', true)->whereIn('slug', $selectedOptionalSlugs)->get() as $opt) {
            $lines[] = ['label' => $opt->name, 'amount' => (float) $opt->amount];
        }
        $lines[] = ['label' => __('Total'), 'amount' => (float) $calc['amount_due'], 'bold' => true];

        return $lines;
    }
}
