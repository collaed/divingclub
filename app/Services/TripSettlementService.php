<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\PaymentExpected;
use App\Models\TripParticipant;
use Illuminate\Support\Collection;

class TripSettlementService
{
    /**
     * Calculate the full settlement for a trip event.
     *
     * @return array{
     *     global_pool: float,
     *     transit_pool: float,
     *     local_subsidy: float,
     *     driver_bounties: float,
     *     net_transit_cost: float,
     *     participants: array<int, array{
     *         user_id: int|null,
     *         name: string,
     *         transit_mode: string,
     *         driving_percentage: int,
     *         global_share: float,
     *         transit_share: float,
     *         local_charge: float,
     *         individual_charges: float,
     *         bounty_credit: float,
     *         instructor_subsidy: float,
     *         prepaid: float,
     *         total_paid: float,
     *         balance: float,
     *         cancelled: bool
     *     }>
     * }
     */
    public function calculate(Event $event): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, TripParticipant> $participants */
        $participants = $event->tripParticipants()->with('user.detail')->get();
        $receipts = $event->tripReceipts()->where('status', 'approved')->get();

        // Build registration lookup — by user_id for members, by non_member_name for non-members
        $registrations = EventRegistration::where('event_id', $event->id)->get();
        $regByUser = $registrations->whereNotNull('user_id')->keyBy('user_id');

        // Prepaid amounts from payment system (deposits paid before trip)
        $prepaidByUser = PaymentExpected::query()
            ->where('event_id', $event->id)
            ->where('amount_paid', '>', 0)
            ->pluck('amount_paid', 'user_id');

        $bountyTotal = (float) ($event->driver_bounty_total ?? 0);
        $dailyCharge = (float) ($event->local_daily_charge ?? 0);

        // Determine which participants are cancelled (they don't share costs)
        $activeParticipants = $participants->filter(function (TripParticipant $p) use ($regByUser, $registrations): bool {
            if ($p->user_id) {
                return ($regByUser[$p->user_id]?->status ?? 'confirmed') !== 'cancelled';
            }
            $reg = $registrations->firstWhere('non_member_name', $p->non_member_name);

            return ($reg?->status ?? 'confirmed') !== 'cancelled';
        });

        // Step 1: Global Pool — shared expenses divided equally among active participants
        $globalReceipts = $receipts->where('category', 'general');
        $globalPool = $globalReceipts->sum('approved_amount');
        $globalShare = $activeParticipants->count() > 0
            ? round($globalPool / $activeParticipants->count(), 2)
            : 0;

        // Step 2: Local Transit Subsidy — fly-in members pay daily charge
        $localSubsidy = 0;
        foreach ($activeParticipants as $p) {
            $mode = $this->getTransitMode($p, $regByUser, $registrations);
            if ($mode !== 'van') {
                $localSubsidy += $p->local_transit_days * $dailyCharge;
            }
        }

        // Step 3: Long-Haul Transit Pool
        $transitReceipts = $receipts->where('category', 'transit');
        $transitPool = $transitReceipts->sum('approved_amount');

        // Step 4: Driver Bounties (distributed by percentage)
        $totalDrivingPct = $activeParticipants->sum('driving_percentage');
        $totalBounties = $totalDrivingPct > 0 ? $bountyTotal : 0;

        // Net transit cost for van passengers = transit expenses + bounties - local subsidy
        $netTransitCost = $transitPool + $totalBounties - $localSubsidy;
        $vanParticipants = $activeParticipants->filter(
            fn (TripParticipant $p): bool => $this->getTransitMode($p, $regByUser, $registrations) === 'van'
        );
        $transitShare = $vanParticipants->count() > 0
            ? round($netTransitCost / $vanParticipants->count(), 2)
            : 0;

        // Step 5: Final Balance per participant
        $result = [];
        foreach ($participants as $p) {
            // Check if this participant's registration is cancelled
            $isCancelled = false;
            if ($p->user_id) {
                $isCancelled = ($regByUser[$p->user_id]?->status ?? '') === 'cancelled';
            } else {
                $reg = $registrations->firstWhere('non_member_name', $p->non_member_name);
                $isCancelled = ($reg?->status ?? '') === 'cancelled';
            }

            $mode = $this->getTransitMode($p, $regByUser, $registrations);
            $isVan = $mode === 'van';
            $bountyCredit = $totalDrivingPct > 0
                ? round($bountyTotal * $p->driving_percentage / $totalDrivingPct, 2)
                : 0;
            $localCharge = $isVan ? 0 : $p->local_transit_days * $dailyCharge;

            // What this person paid (approved receipts, excluding third-party and individual)
            $totalPaid = $p->user_id
                ? $receipts->where('user_id', $p->user_id)->where('is_third_party', false)->where('category', '!=', 'individual')->sum('approved_amount')
                : 0;

            // Individual charges assigned to this person (extras: drinks, personal costs)
            $individualCharges = $p->user_id
                ? (float) $receipts->where('user_id', $p->user_id)->where('category', 'individual')->sum('approved_amount')
                : 0;

            // Prepaid deposits from the payment system
            $prepaid = $p->user_id ? (float) ($prepaidByUser[$p->user_id] ?? 0) : 0;
            // Also check prepaid_amount on the participant itself (for non-members)
            $prepaid += (float) ($p->prepaid_amount ?? 0);

            // Cancelled participants owe nothing — only show refund of prepaid
            if ($isCancelled) {
                $balance = round(0 - $prepaid - $totalPaid, 2);
                $result[] = [
                    'user_id' => $p->user_id,
                    'name' => $p->participantName(),
                    'transit_mode' => $mode,
                    'driving_percentage' => 0,
                    'global_share' => 0,
                    'transit_share' => 0,
                    'local_charge' => 0,
                    'individual_charges' => 0,
                    'bounty_credit' => 0,
                    'instructor_subsidy' => 0,
                    'prepaid' => $prepaid,
                    'total_paid' => (float) $totalPaid,
                    'balance' => $balance,
                    'cancelled' => true,
                ];

                continue;
            }

            // Instructor daily subsidy (contribution to dive costs for supervising instructors)
            $instructorSubsidy = 0;
            $isInstructor = (bool) ($p->user?->detail?->active_instructor ?? false);
            if ($isInstructor && ($event->instructor_daily_subsidy ?? 0) > 0) {
                $tripDays = $event->event_date->diffInDays($event->end_date ?? $event->event_date) ?: 1;
                $instructorSubsidy = round($event->instructor_daily_subsidy * $tripDays, 2);
            }

            // What this person owes
            $owes = $globalShare + ($isVan ? $transitShare : $localCharge) + $individualCharges;

            // Credits: bounty + prepaid + what they paid + instructor subsidy
            $credits = $bountyCredit + $prepaid + $totalPaid + $instructorSubsidy;

            $balance = round($owes - $credits, 2);

            $result[] = [
                'user_id' => $p->user_id,
                'name' => $p->participantName(),
                'transit_mode' => $mode,
                'driving_percentage' => $p->driving_percentage,
                'global_share' => $globalShare,
                'transit_share' => $isVan ? $transitShare : 0,
                'local_charge' => $localCharge,
                'individual_charges' => $individualCharges,
                'bounty_credit' => $bountyCredit,
                'instructor_subsidy' => $instructorSubsidy,
                'prepaid' => $prepaid,
                'total_paid' => (float) $totalPaid,
                'balance' => $balance,
                'cancelled' => false,
            ];
        }

        return [
            'global_pool' => (float) $globalPool,
            'transit_pool' => (float) $transitPool,
            'local_subsidy' => $localSubsidy,
            'driver_bounties' => $totalBounties,
            'net_transit_cost' => $netTransitCost,
            'participants' => $result,
        ];
    }

    /**
     * @param  Collection<int, EventRegistration>  $regByUser
     * @param  Collection<int, EventRegistration>  $allRegistrations
     */
    private function getTransitMode(TripParticipant $p, $regByUser, $allRegistrations): string
    {
        if ($p->user_id) {
            return $regByUser[$p->user_id]?->transit_mode ?? 'van';
        }

        // Non-member: match by name
        $reg = $allRegistrations->firstWhere('non_member_name', $p->non_member_name);

        return $reg?->transit_mode ?? 'van';
    }
}
