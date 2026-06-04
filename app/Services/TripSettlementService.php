<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\TripParticipant;

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
     *         user_id: int,
     *         name: string,
     *         transit_mode: string,
     *         driving_percentage: int,
     *         global_share: float,
     *         transit_share: float,
     *         local_charge: float,
     *         bounty_credit: float,
     *         total_paid: float,
     *         balance: float
     *     }>
     * }
     */
    public function calculate(Event $event): array
    {
        $participants = $event->tripParticipants()->with('user.detail')->get();
        $receipts = $event->tripReceipts()->where('status', 'approved')->get();
        $registrations = EventRegistration::where('event_id', $event->id)
            ->whereIn('user_id', $participants->pluck('user_id'))
            ->get()
            ->keyBy('user_id');

        $bountyTotal = (float) ($event->driver_bounty_total ?? 0);
        $dailyCharge = (float) ($event->local_daily_charge ?? 0);

        // Step 1: Global Pool — shared expenses divided equally
        $globalReceipts = $receipts->where('category', 'general');
        $globalPool = $globalReceipts->sum('approved_amount');
        $globalShare = $participants->count() > 0
            ? round($globalPool / $participants->count(), 2)
            : 0;

        // Step 2: Local Transit Subsidy — fly-in members pay daily charge
        $localSubsidy = 0;
        foreach ($participants as $p) {
            $mode = $registrations[$p->user_id]?->transit_mode ?? 'van';
            if ($mode !== 'van') {
                $localSubsidy += $p->local_transit_days * $dailyCharge;
            }
        }

        // Step 3: Long-Haul Transit Pool
        $transitReceipts = $receipts->where('category', 'transit');
        $transitPool = $transitReceipts->sum('approved_amount');

        // Step 4: Driver Bounties (distributed by percentage)
        $totalDrivingPct = $participants->sum('driving_percentage');
        $totalBounties = $totalDrivingPct > 0 ? $bountyTotal : 0;

        // Net transit cost for van passengers = transit expenses + bounties - local subsidy
        $netTransitCost = $transitPool + $totalBounties - $localSubsidy;
        $vanParticipants = $participants->filter(
            fn (TripParticipant $p) => ($registrations[$p->user_id]?->transit_mode ?? 'van') === 'van'
        );
        $transitShare = $vanParticipants->count() > 0
            ? round($netTransitCost / $vanParticipants->count(), 2)
            : 0;

        // Step 5: Final Balance per participant
        $result = [];
        foreach ($participants as $p) {
            $mode = $registrations[$p->user_id]?->transit_mode ?? 'van';
            $isVan = $mode === 'van';
            $bountyCredit = $totalDrivingPct > 0
                ? round($bountyTotal * $p->driving_percentage / $totalDrivingPct, 2)
                : 0;
            $localCharge = ! $isVan ? $p->local_transit_days * $dailyCharge : 0;

            // What this person paid (approved receipts)
            $totalPaid = $receipts->where('user_id', $p->user_id)->sum('approved_amount');

            // What this person owes
            $owes = $globalShare + ($isVan ? $transitShare : $localCharge);

            // Credits: bounty + what they paid
            $credits = $bountyCredit + $totalPaid;

            $balance = round($owes - $credits, 2);

            $detail = $p->user?->detail;
            $result[] = [
                'user_id' => $p->user_id,
                'name' => $detail ? $detail->first_name.' '.$detail->last_name : 'Unknown',
                'transit_mode' => $mode,
                'driving_percentage' => $p->driving_percentage,
                'global_share' => $globalShare,
                'transit_share' => $isVan ? $transitShare : 0,
                'local_charge' => $localCharge,
                'bounty_credit' => $bountyCredit,
                'total_paid' => (float) $totalPaid,
                'balance' => $balance,
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
}
