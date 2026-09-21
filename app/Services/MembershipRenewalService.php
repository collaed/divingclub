<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MembershipFeeComponent;
use App\Models\MemberStatus;
use App\Models\PaymentExpected;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Membership renewal for one dues season: who has not paid yet, what they are
 * expected to pay (their commitment, else last season's options at this
 * season's prices), which insurance a received amount corresponds to, and
 * marking the season as paid. A season is paid when its dues-year label
 * (e.g. "2027" for 2026-2027) is in the member's cotisation_years.
 */
class MembershipRenewalService
{
    private const EPSILON = 0.005;

    /** Honoraire members count as paid from 1 October of the season's start year. */
    private const HONORAIRE_PAID_MONTH = 10;

    public function __construct(private FeeCalculationService $fees) {}

    public function hasPaid(User $user, string $year): bool
    {
        return in_array($year, array_map('strval', $user->detail?->cotisation_years ?? []), true);
    }

    /**
     * Current members (not former, not honoraire) who have not paid the season.
     *
     * @return Collection<int, User>
     */
    public function outstanding(string $year): Collection
    {
        return User::with(['detail', 'status', 'licences'])
            ->whereHas('detail')
            ->where(fn ($q) => $q->whereNull('status_id')->orWhereNotIn('status_id', MemberStatus::inactiveIds()->all()))
            ->whereDoesntHave('status', fn ($q) => $q->where('slug', MemberStatus::HONORAIRE_SLUG))
            ->get()
            ->reject(fn (User $u): bool => $this->hasPaid($u, $year))
            ->sortBy(fn (User $u): string => mb_strtolower(($u->detail?->last_name ?? '').' '.($u->detail?->first_name ?? '')))
            ->values();
    }

    /**
     * The amount to expect: what the member committed to when they did, else
     * their status and last known insurance priced for this season.
     *
     * @return array{amount: float, components: array<string, mixed>, source: string, insurance: string|null}
     */
    public function proposal(User $user, string $year): array
    {
        $committed = PaymentExpected::where('user_id', $user->id)->where('type', 'membership')
            ->where('season_year', $year)->whereIn('status', ['pending', 'partial'])->first();

        if ($committed instanceof PaymentExpected) {
            return [
                'amount' => (float) $committed->amount_due,
                'components' => $committed->components ?? [],
                'source' => 'committed',
                'insurance' => $this->insuranceIn($committed->components ?? []),
            ];
        }

        $insurance = $this->lastInsuranceSlug($user);
        $calc = $this->fees->calculate($user, $year, $insurance ? [$insurance] : []);

        return ['amount' => (float) $calc['amount_due'], 'components' => $calc['components'], 'source' => 'renewal', 'insurance' => $insurance];
    }

    /**
     * Every way the received amount can be explained: the member's commitment,
     * or the base dues plus one insurance tier (or none).
     *
     * @return array<int, array{insurance: string|null, amount: float, components: array<string, mixed>}>
     */
    public function matches(User $user, string $year, float $amount): array
    {
        $found = [];
        foreach ([null, ...$this->insuranceTiers()->pluck('slug')->all()] as $slug) {
            $calc = $this->fees->calculate($user, $year, $slug ? [$slug] : []);
            if (abs((float) $calc['amount_due'] - $amount) < self::EPSILON) {
                $found[] = ['insurance' => $slug, 'amount' => (float) $calc['amount_due'], 'components' => $calc['components']];
            }
        }

        return $found;
    }

    /**
     * What each option would cost, to explain a received amount that matches none.
     *
     * @return array<int, array{insurance: string|null, label: string, amount: float}>
     */
    public function priceList(User $user, string $year): array
    {
        $list = [];
        foreach ([null, ...$this->insuranceTiers()->all()] as $tier) {
            $calc = $this->fees->calculate($user, $year, $tier ? [$tier->slug] : []);
            $list[] = ['insurance' => $tier?->slug, 'label' => $tier?->name ?? __('No insurance'), 'amount' => (float) $calc['amount_due']];
        }

        return $list;
    }

    /** @param  array<string, mixed>  $components */
    public function markPaid(User $user, string $year, float $amount, array $components): PaymentExpected
    {
        $insurance = $this->insuranceIn($components);
        $payment = PaymentExpected::updateOrCreate(
            ['user_id' => $user->id, 'type' => 'membership', 'season_year' => $year],
            [
                'amount_due' => $amount,
                'amount_paid' => $amount,
                'communication' => $this->fees->buildCommunication($user, $year, $insurance ? [$insurance] : []),
                'components' => $components,
                'provisional' => false,
                'status' => 'paid',
                'paid_at' => now(),
                'reconciled_by' => auth()->user()?->name,
                'reconciled_at' => now(),
            ]
        );
        $this->recordSeasonPaid($payment);

        return $payment;
    }

    /**
     * The bureau vouches that the member paid, whatever the amount or channel
     * (hand-to-hand cash, a transfer not yet reconciled, ...). The expected
     * amount is kept, the amount actually received and how it came are recorded.
     *
     * @param  array<string, mixed>  $components
     */
    public function markPaidManually(User $user, string $year, float $received, float $expected, array $components, string $method, ?string $note): PaymentExpected
    {
        $insurance = $this->insuranceIn($components);
        $payment = PaymentExpected::updateOrCreate(
            ['user_id' => $user->id, 'type' => 'membership', 'season_year' => $year],
            [
                'amount_due' => $expected,
                'amount_paid' => $received,
                'communication' => $this->fees->buildCommunication($user, $year, $insurance ? [$insurance] : []),
                'components' => $components,
                'provisional' => false,
                'status' => 'paid',
                'paid_at' => now(),
                'reconciled_by' => auth()->user()?->name,
                'reconciled_at' => now(),
                'payment_method' => $method,
                'note' => $note,
            ]
        );
        $this->recordSeasonPaid($payment);

        return $payment;
    }

    /** Add the season's label to the member when a membership payment is fully paid. */
    public function recordSeasonPaid(PaymentExpected $payment): void
    {
        if ($payment->type !== 'membership' || $payment->status !== 'paid' || ! $payment->season_year) {
            return;
        }

        $user = User::with('detail')->find($payment->user_id);
        if ($user?->detail) {
            $this->addSeasonLabel($user, (string) $payment->season_year);
        }
    }

    /** Mark every honoraire as paid once the season's 1 October has passed. */
    public function markHonorairesPaid(string $year, ?Carbon $today = null): int
    {
        $today ??= Carbon::today();
        if ($today->lt(Carbon::create((int) $year - 1, self::HONORAIRE_PAID_MONTH, 1))) {
            return 0;
        }

        $count = 0;
        User::with('detail')->whereHas('detail')
            ->whereHas('status', fn ($q) => $q->where('slug', MemberStatus::HONORAIRE_SLUG))
            ->get()
            ->each(function (User $u) use ($year, &$count): void {
                if (! $this->hasPaid($u, $year)) {
                    $this->addSeasonLabel($u, $year);
                    $count++;
                }
            });

        return $count;
    }

    private function addSeasonLabel(User $user, string $year): void
    {
        $years = array_map('strval', $user->detail->cotisation_years ?? []);
        if (! in_array($year, $years, true)) {
            $years[] = $year;
            sort($years);
            $user->detail->update(['cotisation_years' => $years]);
        }
    }

    /** @return Collection<int, MembershipFeeComponent> */
    private function insuranceTiers(): Collection
    {
        return MembershipFeeComponent::where('is_optional', true)->where('kind', MembershipFeeComponent::KIND_ASSURANCE)
            ->where('slug', '!=', 'ass_aucune')->orderBy('sort_order')->get();
    }

    /** @param  array<string, mixed>  $components */
    private function insuranceIn(array $components): ?string
    {
        return $this->insuranceTiers()->pluck('slug')->first(fn (string $slug): bool => array_key_exists($slug, $components));
    }

    /** The insurance tier on the member's latest licence, e.g. "Loisir 1 Top" → ass_loisir1top. */
    private function lastInsuranceSlug(User $user): ?string
    {
        $type = $user->licences->sortByDesc('season')->pluck('insurance_type')->first(fn ($t): bool => is_string($t) && $t !== '');
        if (! $type) {
            return null;
        }
        $slug = 'ass_'.strtolower((string) preg_replace('/\s+/', '', $type));

        return $this->insuranceTiers()->contains('slug', $slug) ? $slug : null;
    }
}
