<?php

/**
 * DAN DL7 (Dive Log version 7) export service.
 *
 * Generates text files in the DAN DL7 format for submission to the
 * DAN PDE (Project Dive Exploration) research program. The format is
 * a pipe-delimited text file with header, diver, and dive records.
 *
 * @author ClubCEP.eu
 *
 * @see https://www.diversalertnetwork.org/research/projects/pde/
 */

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class DanExportService
{
    /**
     * Export dive data for one or more users in DAN DL7 format.
     *
     * @param  Collection  $diveGroupMembers  with loaded user, diveGroup.event.diveSite
     */
    public function export($diveGroupMembers): string
    {
        $lines = [];

        // File header
        $lines[] = 'DL7|'.now()->format('Ymd').'|DivingClub Manager 1.0|';

        // Group by user
        $byUser = $diveGroupMembers->groupBy('user_id');

        foreach ($byUser as $memberships) {
            $user = $memberships->first()->user;
            $detail = $user->detail;

            // Diver record: ZDH (Diver Header)
            $lines[] = implode('|', [
                'ZDH',
                $user->id,
                $detail?->last_name ?? '',
                $detail?->first_name ?? '',
                $detail?->date_of_birth?->format('Ymd') ?? '',
                $detail?->sex ?? '',
                '', // height (cm) — not tracked
                '', // weight (kg) — not tracked
            ]);

            // Dive records: ZDL (Dive Log)
            foreach ($memberships as $dgm) {
                $group = $dgm->diveGroup;
                $event = $group->event;
                $site = $event?->diveSite;

                if (! $event) {
                    continue;
                }

                $lines[] = implode('|', [
                    'ZDL',
                    $event->event_date->format('Ymd'),
                    $event->start_time ? substr($event->start_time, 0, 5) : '',
                    $group->planned_depth ?? '',    // max depth (m)
                    '',                              // bottom time (min) — hand-filled
                    $site?->name ?? '',
                    $site?->country ?? '',
                    $site?->water_type ?? '',
                    $group->dive_mode ?? '',
                    '', // surface interval (min)
                    '', // tank start pressure (bar)
                    '', // tank end pressure (bar)
                ]);
            }

            // Diver trailer
            $lines[] = 'ZDT|'.$memberships->count().'|';
        }

        // File trailer
        $lines[] = 'DLT|'.$byUser->count().'|'.$diveGroupMembers->count().'|';

        return implode("\r\n", $lines)."\r\n";
    }
}
