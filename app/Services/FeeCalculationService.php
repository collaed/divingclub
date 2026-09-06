<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MembershipFee;
use App\Models\MembershipFeeComponent;
use App\Models\MemberStatus;
use App\Models\PaymentExpected;
use App\Models\Season;
use App\Models\ThemeSetting;
use App\Models\User;
use Carbon\Carbon;

class FeeCalculationService
{
    public function __construct(private LicenceResolver $licences = new LicenceResolver) {}

    /**
     * Calculate membership dues: club-retained cotisation base + derived
     * federation licences (FFESSM + FLASSA) + optional assurance cover.
     *
     * The cotisation base is tapered by the season schedule (percentage rounded
     * up to the higher euro). The derived FFESSM licence is added at full price;
     * FLASSA is added via its per-component age taper (0 € under 18) and omitted
     * entirely for a non-licensed (sympathisant) order. Selected assurance
     * options are honoured only when the derived licence allows cover.
     *
     * @param  string[]  $selectedOptionalSlugs
     * @return array{amount_due: float, components: array<string, mixed>, communication: string}
     */
    public function calculate(User $user, string $seasonYear, array $selectedOptionalSlugs = [], ?MemberStatus $statusOverride = null): array
    {
        $status = $statusOverride ?? $user->status;

        // Look up the absolute fee for this status and year
        $fee = MembershipFee::where('season_year', $seasonYear)
            ->where('status_id', $status?->id)
            ->first();

        $baseFee = (float) ($fee?->amount ?? 0);

        // Apply season-relative tapering to the club-retained membership base.
        $pct = $this->taperPercentage($seasonYear);
        $baseAfterTaper = $pct >= 100 ? $baseFee : (float) ceil($baseFee * $pct / 100);

        $season = $this->resolveSeason($seasonYear);

        $components = ['membership' => $baseAfterTaper, 'status' => $status?->name ?? '—', 'label' => $fee?->label ?? ''];
        if ($pct < 100) {
            $components['membership_full'] = $baseFee;
            $components['taper_pct'] = $pct;
        }

        // Derive federation licences (FFESSM + FLASSA) from status and age.
        $derivation = $this->deriveLicences($user, $status, $season);
        $derivedTotal = $this->applyDerivedLicences($components, $derivation, $user, $season);

        // Optional assurance cover — honoured only when a licence allows it.
        $optionalTotal = 0.0;
        if ($derivation->assuranceAllowed) {
            $optionals = MembershipFeeComponent::where('is_optional', true)
                ->whereIn('slug', $selectedOptionalSlugs)->get();
            foreach ($optionals as $opt) {
                $amount = $this->componentAmount($opt, $user, $season);
                $optionalTotal += $amount;
                $components[$opt->slug] = $amount;
            }
        }

        $total = round($baseAfterTaper + $derivedTotal + $optionalTotal, 2);

        return [
            'amount_due' => $total,
            'components' => $components,
            'communication' => $this->buildCommunication($user, $seasonYear, $selectedOptionalSlugs),
        ];
    }

    /**
     * Resolve the derived FFESSM/FLASSA outcome for a user's order from their
     * cotisation status and age at the shared prise-de-licence anchor.
     */
    public function deriveLicences(User $user, ?MemberStatus $status, ?Season $season): LicenceDerivation
    {
        $anchor = $this->licenceAnchor($season);
        $dob = $user->detail?->date_of_birth;
        $dob = $dob instanceof Carbon ? $dob : null;

        return $this->licences->resolve($status?->slug ?? '', $dob, $anchor);
    }

    /**
     * Add the derived FFESSM licence and FLASSA lines to the components map and
     * return their combined amount. FLASSA is omitted when not applicable
     * (sympathisant); it is added at its tapered amount (0 € under 18) otherwise.
     *
     * @param  array<string, mixed>  $components
     */
    private function applyDerivedLicences(array &$components, LicenceDerivation $derivation, User $user, ?Season $season): float
    {
        $total = 0.0;

        $ffessm = MembershipFeeComponent::where('slug', $derivation->ffessmSlug)->first();
        if ($ffessm instanceof MembershipFeeComponent) {
            $amount = (float) $ffessm->amount;
            $total += $amount;
            $components[$ffessm->slug] = $amount;
        }
        $components['ffessm_licence'] = $derivation->ffessmSlug;

        if ($derivation->flassaState->isApplicable()) {
            $flassa = MembershipFeeComponent::where('kind', MembershipFeeComponent::KIND_FLASSA)->first();
            if ($flassa instanceof MembershipFeeComponent) {
                $amount = $this->componentAmount($flassa, $user, $season);
                $total += $amount;
                $components[$flassa->slug] = $amount;
            }
        }
        $components['flassa_state'] = $derivation->flassaState->value;

        return $total;
    }

    /**
     * The shared prise-de-licence anchor: the FLASSA component's configured
     * anchor when present, else the season start, else Jan 1 of the season year.
     */
    private function licenceAnchor(?Season $season): Carbon
    {
        $flassa = MembershipFeeComponent::where('kind', MembershipFeeComponent::KIND_FLASSA)->first();

        return $flassa?->age_anchor_date
            ?? $season?->start_date
            ?? Carbon::createFromDate((int) ($season?->year ?? Carbon::today()->year), 1, 1);
    }

    /**
     * Resolve the fee-taper percentage (0-100) for a season year against a
     * reference date. The reference date is today shifted later by the bureau
     * grace offset `dues_cutoff_grace_days` (default 0 days), so the cutoff
     * falls a little later than today to leave the bureau processing time. An
     * absolute freeze via the `fee_taper_reference_date` setting (ISO date)
     * overrides both.
     */
    public function taperPercentage(string $seasonYear, ?Carbon $reference = null): int
    {
        $season = $this->findSeason($seasonYear);
        if (! $season instanceof Season) {
            return 100;
        }

        if ($reference === null) {
            $reference = $this->resolveAsOfDate();
        }

        return $season->taperPercentage($reference);
    }

    /**
     * The taper as_of_date: an absolute pin when set, otherwise today plus the
     * configurable grace offset (see requirements G1, R-T1).
     */
    public function resolveAsOfDate(): Carbon
    {
        $pinned = ThemeSetting::get('fee_taper_reference_date');
        if ($pinned) {
            return Carbon::parse((string) $pinned);
        }

        $grace = (int) ThemeSetting::get('dues_cutoff_grace_days', 0);

        return Carbon::today()->addDays($grace);
    }

    /** Resolve the Season for a season year, if one exists. */
    public function resolveSeason(string $seasonYear): ?Season
    {
        return $this->findSeason($seasonYear);
    }

    /**
     * Look up a Season by its (integer) year. The `seasons.year` column is an
     * integer, so callers passing a non-numeric season token (e.g. a
     * "2025-2026" range string) simply resolve to no season rather than
     * triggering a database type error on strict engines like PostgreSQL.
     */
    private function findSeason(string $seasonYear): ?Season
    {
        if (! ctype_digit($seasonYear)) {
            return null;
        }

        return Season::where('year', (int) $seasonYear)->first();
    }

    /**
     * Resolve the effective amount for a component, applying any per-component
     * age taper. When the member is younger than `taper_below_age` at the
     * anchor date (the component's `age_anchor_date`, falling back to the
     * season start date, then Jan 1 of the season year), the amount is
     * multiplied by `taper_ratio` (0 = free, 0.5 = half). Members with no known
     * date of birth are always charged the full amount.
     */
    public function componentAmount(MembershipFeeComponent $component, User $user, ?Season $season = null): float
    {
        $amount = (float) $component->amount;

        if ($component->taper_below_age === null || $component->taper_ratio === null) {
            return $amount;
        }

        $dob = $user->detail?->date_of_birth;
        if (! $dob instanceof Carbon) {
            return $amount;
        }

        $anchor = $component->age_anchor_date
            ?? $season?->start_date
            ?? Carbon::createFromDate((int) ($season?->year ?? Carbon::today()->year), 1, 1);

        $ageAtAnchor = $dob->diffInYears($anchor);

        if ($ageAtAnchor < $component->taper_below_age) {
            return round($amount * (float) $component->taper_ratio, 2);
        }

        return $amount;
    }

    /**
     * Create/update the membership PaymentExpected for a user. When
     * $provisional is true (a self-committing, not-yet-classified member), the
     * row is flagged for bureau review and a status override may be supplied.
     *
     * @param  string[]  $selectedOptionalSlugs
     */
    public function createPaymentExpected(User $user, string $seasonYear, array $selectedOptionalSlugs = [], bool $provisional = false, ?MemberStatus $statusOverride = null): PaymentExpected
    {
        $calc = $this->calculate($user, $seasonYear, $selectedOptionalSlugs, $statusOverride);

        return PaymentExpected::updateOrCreate(
            ['user_id' => $user->id, 'type' => 'membership', 'season_year' => $seasonYear],
            [
                'amount_due' => $calc['amount_due'],
                'communication' => $calc['communication'],
                'components' => $calc['components'],
                'provisional' => $provisional,
                'status' => 'pending',
            ]
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
    public function breakdown(User $user, string $seasonYear, array $selectedOptionalSlugs = [], ?MemberStatus $statusOverride = null): array
    {
        $calc = $this->calculate($user, $seasonYear, $selectedOptionalSlugs, $statusOverride);
        $components = $calc['components'];
        $lines = [];
        $lines[] = ['label' => __('Membership').' ('.($components['status'] ?? '').')', 'amount' => (float) $components['membership']];
        if (isset($components['taper_pct'])) {
            $lines[] = [
                'label' => __('Reduced rate (:pct%) — full :full€', [
                    'pct' => $components['taper_pct'],
                    'full' => number_format((float) $components['membership_full'], 0),
                ]),
                'amount' => 0.0,
                'muted' => true,
            ];
        }

        // Derived FFESSM licence line.
        $ffessmSlug = $components['ffessm_licence'] ?? null;
        if (is_string($ffessmSlug) && array_key_exists($ffessmSlug, $components)) {
            $ffessm = MembershipFeeComponent::where('slug', $ffessmSlug)->first();
            if ($ffessm instanceof MembershipFeeComponent) {
                $lines[] = ['label' => $ffessm->name ?? $ffessmSlug, 'amount' => (float) $components[$ffessmSlug]];
            }
        }

        // Derived FLASSA line (present unless not_applicable); "incluse" when free.
        $flassaState = $components['flassa_state'] ?? FlassaState::NotApplicable->value;
        if ($flassaState !== FlassaState::NotApplicable->value) {
            $flassa = MembershipFeeComponent::where('kind', MembershipFeeComponent::KIND_FLASSA)->first();
            if ($flassa instanceof MembershipFeeComponent) {
                $amount = (float) ($components[$flassa->slug] ?? 0.0);
                $label = $flassa->name ?? 'FLASSA';
                if ($flassaState === FlassaState::IncludedFree->value) {
                    $label .= ' ('.__('included').')';
                }
                $lines[] = ['label' => $label, 'amount' => $amount, 'muted' => $flassaState === FlassaState::IncludedFree->value];
            }
        }

        // Assurance lines — only present when a licence allows cover.
        foreach (MembershipFeeComponent::where('is_optional', true)->whereIn('slug', $selectedOptionalSlugs)->get() as $opt) {
            if (! array_key_exists($opt->slug, $components)) {
                continue;
            }
            $lines[] = ['label' => $opt->name, 'amount' => (float) $components[$opt->slug]];
        }
        $lines[] = ['label' => __('Total'), 'amount' => (float) $calc['amount_due'], 'bold' => true];

        return $lines;
    }
}
